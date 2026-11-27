<?php

namespace Modules\Accounting\Domain\Enums;

use Modules\Accounting\Domain\Exceptions\InvalidAccountChange;

/**
 * Master-data lifecycle. Archived accounts are history only and can never come back.
 */
enum AccountStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Active => in_array($target, [self::Inactive, self::Archived], true),
            self::Inactive => in_array($target, [self::Active, self::Archived], true),
            self::Archived => false,
        };
    }

    public function assertCanTransitionTo(self $target): void
    {
        if (! $this->canTransitionTo($target)) {
            throw InvalidAccountChange::statusTransition($this, $target);
        }
    }

    public function label(): string
    {
        return __('accounting.account_status.'.$this->value);
    }
}
