<?php

namespace Modules\Platform\Application;

use Illuminate\Support\Facades\DB;
use Modules\Subscriptions\Domain\Models\Plan;

final class SavePlan
{
    public function __construct(private readonly RecordPlatformAudit $audit) {}

    /**
     * Creates or updates a plan. Running subscriptions keep the price and limits they were sold with
     * (they are snapshotted), so editing a plan only affects new and renewed subscriptions.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(?Plan $plan, array $attributes): Plan
    {
        return DB::transaction(function () use ($plan, $attributes): Plan {
            $old = $plan?->only(['price_minor', 'max_users', 'max_companies', 'trial_days', 'is_active']);

            $plan = $plan === null ? Plan::create($attributes) : tap($plan)->update($attributes);

            $this->audit->handle($old === null ? 'plan_created' : 'plan_updated', 'plan', $plan->id, [
                'code' => $plan->code, 'from' => $old, 'to' => $plan->only(['price_minor', 'max_users', 'max_companies', 'trial_days', 'is_active']),
            ]);

            return $plan->refresh();
        });
    }
}
