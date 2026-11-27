<?php

namespace Modules\Localization\Domain;

use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Enums\CashFlowCategory;
use Modules\Accounting\Domain\ValueObjects\AccountSeed;

/**
 * Fluent helper country packs use to declare their chart of accounts. It carries no country rules,
 * only structure: headers (non-postable groups) and postable leaf accounts.
 */
final class ChartBuilder
{
    /** @var list<AccountSeed> */
    private array $seeds = [];

    public function header(string $code, string $nameEn, string $nameAr, AccountType $type, ?string $parent = null): self
    {
        $this->seeds[] = new AccountSeed($code, $nameEn, $nameAr, $type, $parent, false);

        return $this;
    }

    public function leaf(
        string $code,
        string $nameEn,
        string $nameAr,
        AccountType $type,
        string $parent,
        ?AccountRole $role = null,
        CashFlowCategory $cashFlow = CashFlowCategory::None,
    ): self {
        $this->seeds[] = new AccountSeed($code, $nameEn, $nameAr, $type, $parent, true, $cashFlow, $role);

        return $this;
    }

    /** @return list<AccountSeed> */
    public function build(): array
    {
        return $this->seeds;
    }
}
