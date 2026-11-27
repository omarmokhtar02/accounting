<?php

namespace Modules\Subscriptions\Application;

use Illuminate\Support\Facades\DB;
use Modules\Subscriptions\Domain\Exceptions\InvalidSubscriptionChange;
use Modules\Subscriptions\Domain\Models\Plan;
use Modules\Subscriptions\Domain\Models\Subscription;

final class ChangePlan
{
    use RecordsSubscriptionEvents;

    /**
     * Moves a live subscription to another plan, taking the new price and limits. The current
     * trial/period dates are kept: a plan change never grants or removes time.
     */
    public function handle(Subscription $subscription, Plan $plan, ?int $adminId = null): Subscription
    {
        if (! $plan->is_active) {
            throw InvalidSubscriptionChange::planInactive();
        }

        return DB::transaction(function () use ($subscription, $plan, $adminId): Subscription {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $previous = $subscription->plan_id;

            if ($subscription->status->isLive() && Subscription::query()->where('tenant_id', $subscription->tenant_id)->where('plan_id', $plan->id)
                ->whereKeyNot($subscription->id)->whereIn('status', ['trialing', 'active'])->exists()) {
                throw InvalidSubscriptionChange::alreadyLive();
            }

            $subscription->update([
                'plan_id' => $plan->id,
                'price_minor' => $plan->price_minor,
                'currency' => $plan->currency,
                'interval' => $plan->interval,
                'max_users' => $plan->max_users,
                'max_companies' => $plan->max_companies,
            ]);

            $this->recordEvent($subscription, 'plan_changed', ['from_plan_id' => $previous, 'to_plan' => $plan->code], $adminId);

            return $subscription->refresh();
        });
    }
}
