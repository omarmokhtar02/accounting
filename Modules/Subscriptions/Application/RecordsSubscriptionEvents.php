<?php

namespace Modules\Subscriptions\Application;

use Modules\Subscriptions\Domain\Models\Subscription;
use Modules\Subscriptions\Domain\Models\SubscriptionEvent;

/**
 * Appends to the subscription's history. Every state change of a subscription leaves an event so
 * the admin can answer "who extended this trial, when, and why".
 */
trait RecordsSubscriptionEvents
{
    /** @param array<string, mixed> $data */
    protected function recordEvent(Subscription $subscription, string $type, array $data = [], ?int $adminId = null): SubscriptionEvent
    {
        return SubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'type' => $type,
            'data' => $data === [] ? null : $data,
            'admin_user_id' => $adminId,
            'occurred_at' => now(),
        ]);
    }
}
