<?php

namespace Modules\Accounting\Domain\Enums;

use Modules\Accounting\Domain\Exceptions\InvalidJournalState;

enum JournalStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Reversed = 'reversed';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => $target === self::Posted,
            self::Posted => $target === self::Reversed,
            self::Reversed => false,
        };
    }

    public function assertCanTransitionTo(self $target): void
    {
        if (! $this->canTransitionTo($target)) {
            throw InvalidJournalState::transition($this, $target);
        }
    }

    /**
     * Presentation hint for badges: one of the x-ui.badge tones.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Posted => 'success',
            self::Reversed => 'neutral',
        };
    }

    public function label(): string
    {
        return __('accounting.journal_status.'.$this->value);
    }
}
