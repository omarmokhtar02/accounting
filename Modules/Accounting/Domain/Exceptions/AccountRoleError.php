<?php

namespace Modules\Accounting\Domain\Exceptions;

use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Enums\AccountType;

final class AccountRoleError extends DomainException
{
    public static function notMapped(AccountRole $role): self
    {
        return new self('accounting.errors.role_not_mapped', ['role' => $role->label()]);
    }

    public static function wrongType(AccountRole $role, AccountType $expected): self
    {
        return new self('accounting.errors.role_wrong_type', ['role' => $role->label(), 'type' => $expected->label()]);
    }

    public static function notPostable(): self
    {
        return new self('accounting.errors.role_account_not_postable');
    }

    public static function controlAccountManualPosting(string $code): self
    {
        return new self('accounting.errors.control_account_manual', ['code' => $code]);
    }
}
