<?php

namespace Modules\Subscriptions\Application;

use Illuminate\Support\Facades\DB;
use Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use Modules\Subscriptions\Domain\Exceptions\InvalidSubscriptionChange;
use Modules\Subscriptions\Domain\Models\Plan;
use Modules\Subscriptions\Domain\Models\Subscription;

final class ActivateSubscription
{
    use RecordsSubscriptionEvents;

    /**
     * Activates (or renews) a paid subscription for $periods billing intervals. A renewal of a still
     * running subscription extends from its current end, so paying early never loses days.
     */
    public function handle(Subscription $subscription, Plan $plan, int $periods = 1, ?int $adminId = null): Subscription
    {
        if ($periods < 1) {
            throw InvalidSubscriptionChange::invalidDays();
        }

        if (! $plan->is_active) {
            throw InvalidSubscriptionChange::planInactive();
        }

        return DB::transaction(function () use ($subscription, $plan, $periods, $adminId): Subscription {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $renewing = $subscription->status === SubscriptionStatus::Active;

            if (! $renewing) {
                $subscription->status->assertCanTransitionTo(SubscriptionStatus::Active);

                // Reactivating onto a plan the tenant already runs in another subscription is blocked by the unique index; fail clearly.
                if (Subscription::query()->where('tenant_id', $subscription->tenant_id)->where('plan_id', $plan->id)->whereKeyNot($subscription->id)
                    ->whereIn('status', ['trialing', 'active'])->exists()) {
                    throw InvalidSubscriptionChange::alreadyLive();
                }
            }

            $start = $renewing && $subscription->current_period_ends_at?->isFuture() ? $subscription->current_period_ends_at : now();
            $end = $plan->interval === 'year' ? $start->copy()->addYears($periods) : $start->copy()->addMonths($periods);

            $subscription->update([
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'trial_ends_at' => $renewing ? $subscription->trial_ends_at : null,
                'current_period_starts_at' => $renewing ? ($subscription->current_period_starts_at ?? $start) : $start,
                'current_period_ends_at' => $end,
                'canceled_at' => null,
                'price_minor' => $plan->price_minor,
                'currency' => $plan->currency,
                'interval' => $plan->interval,
                'max_users' => $plan->max_users,
                'max_companies' => $plan->max_companies,
            ]);

            $this->recordEvent($subscription, $renewing ? 'renewed' : 'activated', ['plan' => $plan->code, 'periods' => $periods, 'ends_at' => $end->toIso8601String()], $adminId);

            return $subscription->refresh();
        });
    }
}
