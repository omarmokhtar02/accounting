<?php

namespace Modules\Subscriptions\Application;

use Illuminate\Support\Facades\DB;
use Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use Modules\Subscriptions\Domain\Exceptions\InvalidSubscriptionChange;
use Modules\Subscriptions\Domain\Models\Subscription;

final class ExtendTrial
{
    use RecordsSubscriptionEvents;

    /**
     * Gives a tenant more trial days. Works on a running trial (adds to the remaining time) and on a
     * lapsed one (reopens it from now). Paying customers are not on a trial, so it is refused for them.
     */
    public function handle(Subscription $subscription, int $days, ?string $reason = null, ?int $adminId = null): Subscription
    {
        if ($days < 1 || $days > 365) {
            throw InvalidSubscriptionChange::invalidDays();
        }

        return DB::transaction(function () use ($subscription, $days, $reason, $adminId): Subscription {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);

            if ($subscription->status === SubscriptionStatus::Active || ($subscription->status === SubscriptionStatus::Canceled && $subscription->trial_ends_at === null)) {
                throw InvalidSubscriptionChange::notTrialing();
            }

            if ($subscription->status !== SubscriptionStatus::Trialing) {
                $subscription->status->assertCanTransitionTo(SubscriptionStatus::Trialing);
            }

            $base = $subscription->status === SubscriptionStatus::Trialing && $subscription->trial_ends_at?->isFuture()
                ? $subscription->trial_ends_at
                : now();

            $subscription->update([
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => $base->copy()->addDays($days),
                'canceled_at' => null,
            ]);

            $this->recordEvent($subscription, 'trial_extended', ['days' => $days, 'reason' => $reason, 'ends_at' => $subscription->trial_ends_at->toIso8601String()], $adminId);

            return $subscription->refresh();
        });
    }
}
