<?php

namespace Modules\Platform\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Platform\Application\SavePlan;
use Modules\Shared\Domain\Contracts\CurrencyRegistry;
use Modules\Shared\Domain\Money;
use Modules\Subscriptions\Domain\Models\Plan;

class PlanController extends Controller
{
    public function index(): View
    {
        return view('platform::plans.index', ['plans' => Plan::query()->withCount('subscriptions')->orderBy('sort_order')->get()]);
    }

    public function create(): View
    {
        return view('platform::plans.form', ['plan' => new Plan(['currency' => 'USD', 'interval' => 'month', 'is_active' => true]), 'price' => '0.00']);
    }

    public function store(Request $request, SavePlan $savePlan): RedirectResponse
    {
        $savePlan->handle(null, $this->attributes($request));

        return redirect()->route('platform.plans.index')->with('status', __('platform.plan_saved'));
    }

    public function edit(Plan $plan, CurrencyRegistry $currencies): View
    {
        return view('platform::plans.form', ['plan' => $plan, 'price' => (new Money($plan->price_minor, $currencies->get($plan->currency)))->toDecimal()]);
    }

    public function update(Request $request, Plan $plan, SavePlan $savePlan): RedirectResponse
    {
        $savePlan->handle($plan, $this->attributes($request, $plan));

        return redirect()->route('platform.plans.index')->with('status', __('platform.plan_saved'));
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(Request $request, ?Plan $plan = null): array
    {
        $validated = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('plans', 'code')->ignore($plan)],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'description_en' => ['nullable', 'string', 'max:1000'],
            'description_ar' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'regex:/^\d{1,10}(\.\d{1,3})?$/'],
            'currency' => ['required', Rule::exists('currencies', 'code')],
            'interval' => ['required', Rule::in(['month', 'year'])],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_companies' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $currency = app(CurrencyRegistry::class)->get($validated['currency']);

        return [
            'code' => $validated['code'],
            'name_en' => $validated['name_en'],
            'name_ar' => $validated['name_ar'],
            'description_en' => $validated['description_en'] ?? null,
            'description_ar' => $validated['description_ar'] ?? null,
            'price_minor' => Money::fromDecimal($validated['price'], $currency)->minor,
            'currency' => $validated['currency'],
            'interval' => $validated['interval'],
            'trial_days' => $validated['trial_days'] ?? null,
            'max_users' => $validated['max_users'] ?? null,
            'max_companies' => $validated['max_companies'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
