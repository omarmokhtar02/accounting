<?php

namespace Modules\Platform\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Platform\Application\SetTenantStatus;
use Modules\Shared\Domain\Contracts\CurrencyRegistry;
use Modules\Shared\Domain\Exceptions\InvalidMoneyAmount;
use Modules\Shared\Domain\Money;
use Modules\Subscriptions\Application\ActivateSubscription;
use Modules\Subscriptions\Application\CancelSubscription;
use Modules\Subscriptions\Application\ChangePlan;
use Modules\Subscriptions\Application\ExtendTrial;
use Modules\Subscriptions\Application\GrantAllPlans;
use Modules\Subscriptions\Application\GrantPlan;
use Modules\Subscriptions\Application\RecordPayment;
use Modules\Subscriptions\Application\SubscriptionAccess;
use Modules\Subscriptions\Domain\Exceptions\InvalidSubscriptionChange;
use Modules\Subscriptions\Domain\Models\Plan;
use Modules\Subscriptions\Domain\Models\Subscription;
use Modules\Subscriptions\Domain\Models\SubscriptionEvent;
use Modules\Subscriptions\Domain\Models\SubscriptionPayment;
use Modules\Tenancy\Domain\Models\Company;
use Modules\Tenancy\Domain\Models\Tenant;

class TenantController extends Controller
{
    public function index(Request $request, SubscriptionAccess $access): View
    {
        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status');

        $tenants = Tenant::query()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($status === 'none', fn ($q) => $q->whereNotIn('id', Subscription::query()->select('tenant_id')))
            ->when($status !== '' && $status !== 'none', fn ($q) => $q->whereIn('id', Subscription::query()->where('status', $status)->select('tenant_id')))
            ->orderByDesc('id')->paginate(25)->withQueryString();

        $grouped = Subscription::with('plan')->whereIn('tenant_id', $tenants->pluck('id'))->orderBy('id')->get()->groupBy('tenant_id');
        $subscriptions = $grouped->map(fn ($subs) => $subs->first(fn ($s) => $s->status->isLive()) ?? $subs->last());
        // A company can run several plans at once: the list shows all the live ones.
        $livePlans = $grouped->map(fn ($subs) => $subs->filter(fn ($s) => $s->status->isLive())->map(fn ($s) => $s->plan->localizedName())->values());

        return view('platform::tenants.index', [
            'tenants' => $tenants, 'subscriptions' => $subscriptions, 'livePlans' => $livePlans, 'search' => $search, 'status' => $status,
        ]);
    }

    public function show(Tenant $tenant, SubscriptionAccess $access): View
    {
        $subscriptions = $access->allFor($tenant->id);
        $subscription = $subscriptions->first();

        return view('platform::tenants.show', [
            'tenant' => $tenant,
            'subscription' => $subscription,
            'subscriptions' => $access->allFor($tenant->id),
            'level' => $access->levelFor($tenant->id),
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'users' => User::query()->where('tenant_id', $tenant->id)->with('companies')->orderBy('name')->get(),
            'companies' => Company::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'events' => SubscriptionEvent::query()->whereIn('subscription_id', $subscriptions->pluck('id'))->with('subscription.plan')->latest('occurred_at')->latest('id')->limit(50)->get(),
            'payments' => SubscriptionPayment::query()->whereIn('subscription_id', $subscriptions->pluck('id'))->with('subscription.plan')->latest('paid_at')->get(),
        ]);
    }

    public function suspend(Request $request, Tenant $tenant, SetTenantStatus $setStatus): RedirectResponse
    {
        $setStatus->suspend($tenant, $request->input('reason'));

        return back()->with('status', __('platform.tenant_suspended'));
    }

    public function activate(Tenant $tenant, SetTenantStatus $setStatus): RedirectResponse
    {
        $setStatus->activate($tenant);

        return back()->with('status', __('platform.tenant_activated'));
    }

    public function extendTrial(Request $request, Tenant $tenant, ExtendTrial $extendTrial, SubscriptionAccess $access): RedirectResponse
    {
        $validated = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:365'], 'reason' => ['nullable', 'string', 'max:255']]);

        return $this->run(fn () => $extendTrial->handle($this->subscription($tenant, $access, $request->integer('subscription_id') ?: null), (int) $validated['days'], $validated['reason'] ?? null, $request->user('admin')->id), 'platform.trial_extended');
    }

    public function changePlan(Request $request, Tenant $tenant, ChangePlan $changePlan, SubscriptionAccess $access): RedirectResponse
    {
        $validated = $request->validate(['plan_id' => ['required', Rule::exists('plans', 'id')]]);

        return $this->run(fn () => $changePlan->handle($this->subscription($tenant, $access, $request->integer('subscription_id') ?: null), Plan::findOrFail($validated['plan_id']), $request->user('admin')->id), 'platform.plan_changed');
    }

    public function activateSubscription(Request $request, Tenant $tenant, ActivateSubscription $activate, SubscriptionAccess $access): RedirectResponse
    {
        $validated = $request->validate(['plan_id' => ['required', Rule::exists('plans', 'id')], 'periods' => ['required', 'integer', 'min:1', 'max:60']]);

        return $this->run(fn () => $activate->handle($this->subscription($tenant, $access, $request->integer('subscription_id') ?: null), Plan::findOrFail($validated['plan_id']), (int) $validated['periods'], $request->user('admin')->id), 'platform.subscription_activated');
    }

    public function grantPlan(Request $request, Tenant $tenant, GrantPlan $grant): RedirectResponse
    {
        $validated = $request->validate(['plan_id' => ['required', Rule::exists('plans', 'id')], 'periods' => ['required', 'integer', 'min:1', 'max:60']]);

        return $this->run(fn () => $grant->handle($tenant->id, Plan::findOrFail($validated['plan_id']), (int) $validated['periods'], $request->user('admin')->id), 'platform.plan_granted');
    }

    public function grantAllPlans(Request $request, Tenant $tenant, GrantAllPlans $grantAll): RedirectResponse
    {
        $validated = $request->validate(['periods' => ['required', 'integer', 'min:1', 'max:60']]);

        return $this->run(fn () => $grantAll->handle($tenant->id, (int) $validated['periods'], $request->user('admin')->id), 'platform.all_plans_granted');
    }

    public function cancel(Request $request, Tenant $tenant, CancelSubscription $cancel, SubscriptionAccess $access): RedirectResponse
    {
        return $this->run(fn () => $cancel->handle($this->subscription($tenant, $access, $request->integer('subscription_id') ?: null), $request->input('reason'), $request->user('admin')->id), 'platform.subscription_canceled');
    }

    public function recordPayment(Request $request, Tenant $tenant, RecordPayment $recordPayment, SubscriptionAccess $access): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'regex:/^\d{1,10}(\.\d{1,3})?$/'],
            'method' => ['required', Rule::in(['bank_transfer', 'cash', 'cheque', 'card', 'other'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['required', 'date'],
        ]);

        return $this->run(function () use ($validated, $tenant, $access, $recordPayment, $request) {
            $subscription = $this->subscription($tenant, $access, $request->integer('subscription_id') ?: null);
            $currency = app(CurrencyRegistry::class)->get($subscription->currency);
            $minor = Money::fromDecimal($validated['amount'], $currency)->minor;

            $recordPayment->handle($subscription, $minor, $validated['method'], $validated['reference'] ?? null, CarbonImmutable::parse($validated['paid_at']), $request->user('admin')->id);
        }, 'platform.payment_recorded');
    }

    /** The subscription named by the form (it must belong to the tenant), otherwise the one that decides access. */
    private function subscription(Tenant $tenant, SubscriptionAccess $access, ?int $subscriptionId = null): Subscription
    {
        if ($subscriptionId !== null) {
            return Subscription::query()->where('tenant_id', $tenant->id)->findOrFail($subscriptionId);
        }

        return $access->currentFor($tenant->id) ?? throw InvalidSubscriptionChange::noSubscription();
    }

    private function run(callable $action, string $successKey): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidSubscriptionChange $e) {
            return back()->withErrors(['subscription' => $e->userMessage()]);
        } catch (InvalidMoneyAmount $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', __($successKey));
    }
}
