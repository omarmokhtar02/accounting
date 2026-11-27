<?php

namespace Modules\Subscriptions\Domain\Enums;

use Modules\Subscriptions\Domain\Exceptions\InvalidSubscriptionChange;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case Expired = 'expired';
    case Canceled = 'canceled';

    /** A tenant may have only one live subscription at a time. */
    public function isLive(): bool
    {
        return $this === self::Trialing || $this === self::Active;
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Trialing => in_array($target, [self::Active, self::Expired, self::Canceled], true),
            self::Active => in_array($target, [self::Expired, self::Canceled], true),
            // An admin can reopen a lapsed tenant: pay and activate, or grant another trial.
            self::Expired, self::Canceled => in_array($target, [self::Active, self::Trialing], true),
        };
    }

    public function assertCanTransitionTo(self $target): void
    {
        if (! $this->canTransitionTo($target)) {
            throw InvalidSubscriptionChange::transition($this, $target);
        }
    }

    public function tone(): string
    {
        return match ($this) {
            self::Trialing => 'warning',
            self::Active => 'success',
            self::Expired => 'danger',
            self::Canceled => 'neutral',
        };
    }

    public function label(): string
    {
        return __('subscriptions.status.'.$this->value);
    }
}
