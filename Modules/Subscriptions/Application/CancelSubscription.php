<?php

namespace Modules\Subscriptions\Application;

use Illuminate\Support\Facades\DB;
use Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use Modules\Subscriptions\Domain\Models\Subscription;

final class CancelSubscription
{
    use RecordsSubscriptionEvents;

    /**
     * Cancels at the end of what has been paid for: access continues until the current period (or trial)
     * ends, then the normal expiry/grace rules apply.
     */
    public function handle(Subscription $subscription, ?string $reason = null, ?int $adminId = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $reason, $adminId): Subscription {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $subscription->status->assertCanTransitionTo(SubscriptionStatus::Canceled);

            $subscription->update(['status' => SubscriptionStatus::Canceled, 'canceled_at' => now()]);

            $this->recordEvent($subscription, 'canceled', ['reason' => $reason], $adminId);

            return $subscription->refresh();
        });
    }
}
