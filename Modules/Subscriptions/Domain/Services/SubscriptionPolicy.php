<?php

namespace Modules\Subscriptions\Domain\Services;

use Carbon\CarbonInterface;
use Modules\Subscriptions\Domain\Enums\AccessLevel;
use Modules\Subscriptions\Domain\Enums\SubscriptionStatus;

/**
 * Pure rule: given a subscription's status and end date, what may the tenant do at a given moment?
 * Full access until the end; then a read-only grace period (so the customer can still see and
 * export their books); then only the billing page.
 */
final class SubscriptionPolicy
{
    public function accessLevel(SubscriptionStatus $status, ?CarbonInterface $accessEndsAt, CarbonInterface $now, int $graceDays): AccessLevel
    {
        // Open-ended live subscription (no end date): never expires on its own.
        if ($accessEndsAt === null) {
            return $status->isLive() ? AccessLevel::Full : AccessLevel::Blocked;
        }

        if ($status->isLive() || $status === SubscriptionStatus::Canceled) {
            if ($now->lessThanOrEqualTo($accessEndsAt)) {
                return AccessLevel::Full;
            }
        }

        if ($now->lessThanOrEqualTo($accessEndsAt->copy()->addDays($graceDays))) {
            return AccessLevel::ReadOnly;
        }

        return AccessLevel::Blocked;
    }
}
