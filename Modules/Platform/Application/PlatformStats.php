<?php

namespace Modules\Platform\Application;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Subscriptions\Domain\Models\Subscription;
use Modules\Tenancy\Domain\Models\Company;
use Modules\Tenancy\Domain\Models\Tenant;

/**
 * Read model for the admin dashboard. Everything is derived from tenants and subscriptions, never stored.
 */
final class PlatformStats
{
    /**
     * @return array{
     *     tenants: array{total: int, suspended: int, no_subscription: int},
     *     subscriptions: array{trialing: int, active: int, expired: int, canceled: int},
     *     users: int, companies: int, signups_30d: int,
     *     mrr: array<string, int>,
     *     trials_ending: Collection<int, Subscription>,
     *     recent_signups: Collection<int, Tenant>,
     *     recently_expired: Collection<int, Subscription>
     * }
     */
    public function snapshot(int $trialWarningDays = 7): array
    {
        $current = $this->currentSubscriptions();

        return [
            'tenants' => [
                'total' => Tenant::count(),
                'suspended' => Tenant::where('status', '!=', 'active')->count(),
                'no_subscription' => Tenant::whereNotIn('id', $current->pluck('tenant_id'))->count(),
            ],
            'subscriptions' => [
                'trialing' => $current->where('status.value', 'trialing')->count(),
                'active' => $current->where('status.value', 'active')->count(),
                'expired' => $current->where('status.value', 'expired')->count(),
                'canceled' => $current->where('status.value', 'canceled')->count(),
            ],
            'users' => User::count(),
            'companies' => Company::withoutGlobalScopes()->count(),
            'signups_30d' => Tenant::where('created_at', '>=', now()->subDays(30))->count(),
            'mrr' => $this->monthlyRecurringRevenue(Subscription::query()->where('status', 'active')->get()),
            'trials_ending' => Subscription::with('tenant')->where('status', 'trialing')
                ->whereBetween('trial_ends_at', [now(), now()->addDays($trialWarningDays)])->orderBy('trial_ends_at')->limit(10)->get(),
            'recent_signups' => Tenant::latest('id')->limit(10)->get(),
            'recently_expired' => Subscription::with('tenant')->where('status', 'expired')->latest('updated_at')->limit(10)->get(),
        ];
    }

    /**
     * One subscription per tenant: the live one, otherwise the latest.
     *
     * @return Collection<int, Subscription>
     */
    private function currentSubscriptions(): Collection
    {
        return Subscription::query()->orderByDesc('id')->get()
            ->groupBy('tenant_id')
            ->map(fn (Collection $subs) => $subs->first(fn (Subscription $s) => $s->status->isLive()) ?? $subs->first())
            ->values();
    }

    /**
     * Monthly recurring revenue of ACTIVE paying subscriptions only (trials earn nothing), per
     * currency, with yearly plans spread over twelve months. Integer minor units.
     *
     * @param  Collection<int, Subscription>  $current
     * @return array<string, int>
     */
    private function monthlyRecurringRevenue(Collection $current): array
    {
        $mrr = [];

        foreach ($current->where('status.value', 'active') as $subscription) {
            $monthly = $subscription->interval === 'year' ? intdiv($subscription->price_minor, 12) : $subscription->price_minor;
            $mrr[$subscription->currency] = ($mrr[$subscription->currency] ?? 0) + $monthly;
        }

        ksort($mrr);

        return $mrr;
    }
}
