<?php

namespace Modules\Subscriptions\Application;

use Illuminate\Support\Facades\DB;
use Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use Modules\Subscriptions\Domain\Exceptions\InvalidSubscriptionChange;
use Modules\Subscriptions\Domain\Models\Plan;
use Modules\Subscriptions\Domain\Models\Subscription;

final class GrantPlan
{
    use RecordsSubscriptionEvents;

    public function __construct(private readonly ActivateSubscription $activate) {}

    /**
     * Gives a tenant access to a plan for $periods billing intervals, next to any plans it already has.
     * When the tenant already runs this plan (trial or paid) that subscription is activated or extended
     * instead of creating a second one.
     */
    public function handle(int $tenantId, Plan $plan, int $periods = 1, ?int $adminId = null): Subscription
    {
        if ($periods < 1) {
            throw InvalidSubscriptionChange::invalidDays();
        }

        if (! $plan->is_active) {
            throw InvalidSubscriptionChange::planInactive();
        }

        return DB::transaction(function () use ($tenantId, $plan, $periods, $adminId): Subscription {
            $running = Subscription::query()->where('tenant_id', $tenantId)->where('plan_id', $plan->id)
                ->whereIn('status', ['trialing', 'active'])->lockForUpdate()->first();

            if ($running !== null) {
                return $this->activate->handle($running, $plan, $periods, $adminId);
            }

            $start = now();
            $end = $plan->interval === 'year' ? $start->copy()->addYears($periods) : $start->copy()->addMonths($periods);

            $subscription = Subscription::create([
                'tenant_id' => $tenantId,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'current_period_starts_at' => $start,
                'current_period_ends_at' => $end,
                'price_minor' => $plan->price_minor,
                'currency' => $plan->currency,
                'interval' => $plan->interval,
                'max_users' => $plan->max_users,
                'max_companies' => $plan->max_companies,
            ]);

            $this->recordEvent($subscription, 'granted', ['plan' => $plan->code, 'periods' => $periods, 'ends_at' => $end->toIso8601String()], $adminId);

            return $subscription;
        });
    }
}
