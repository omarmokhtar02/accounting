<?php

namespace Modules\Subscriptions\Application;

use Illuminate\Support\Facades\DB;
use Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use Modules\Subscriptions\Domain\Models\Subscription;

final class ExpireDueSubscriptions
{
    use RecordsSubscriptionEvents;

    /**
     * Marks live subscriptions whose trial or paid period has ended as expired. Safe to run as often as
     * you like (idempotent) and never touches a subscription that was renewed meanwhile.
     *
     * @return int number of subscriptions expired
     */
    public function handle(): int
    {
        $count = 0;

        Subscription::query()->whereIn('status', ['trialing', 'active'])->get()->each(function (Subscription $candidate) use (&$count): void {
            DB::transaction(function () use ($candidate, &$count): void {
                $subscription = Subscription::query()->lockForUpdate()->find($candidate->id);
                $end = $subscription?->accessEndsAt();

                if ($subscription === null || ! $subscription->status->isLive() || $end === null || $end->isFuture()) {
                    return;
                }

                $was = $subscription->status;
                $subscription->update(['status' => SubscriptionStatus::Expired]);
                $this->recordEvent($subscription, 'expired', ['was' => $was->value, 'ended_at' => $end->toIso8601String()]);
                $count++;
            });
        });

        return $count;
    }
}
