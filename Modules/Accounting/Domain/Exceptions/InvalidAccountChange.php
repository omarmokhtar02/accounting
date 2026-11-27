<?php

namespace Modules\Accounting\Domain\Exceptions;

use Modules\Accounting\Domain\Enums\AccountStatus;

final class InvalidAccountChange extends DomainException
{
    public static function statusTransition(AccountStatus $from, AccountStatus $to): self
    {
        return new self('accounting.errors.account_status_transition', ['from' => $from->label(), 'to' => $to->label()]);
    }

    public static function lockedAfterUse(string $field): self
    {
        return new self('accounting.errors.account_locked_after_use', ['field' => $field]);
    }

    public static function archiveWithBalance(): self
    {
        return new self('accounting.errors.account_archive_with_balance');
    }

    public static function parentCycle(): self
    {
        return new self('accounting.errors.account_parent_cycle');
    }

    public static function hasChildren(): self
    {
        return new self('accounting.errors.account_has_children');
    }

    public static function parentNotAllowed(): self
    {
        return new self('accounting.errors.account_parent_invalid');
    }
}
