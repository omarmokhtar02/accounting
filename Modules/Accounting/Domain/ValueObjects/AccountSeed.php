<?php

namespace Modules\Accounting\Domain\ValueObjects;

use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Enums\CashFlowCategory;

/**
 * One account of a chart-of-accounts template (supplied by a country pack), before it exists in a
 * company. Parents are referenced by code so a template is a plain, order-independent list.
 */
final readonly class AccountSeed
{
    public function __construct(
        public string $code,
        public string $nameEn,
        public string $nameAr,
        public AccountType $type,
        public ?string $parentCode = null,
        public bool $postable = true,
        public CashFlowCategory $cashFlow = CashFlowCategory::None,
        public ?AccountRole $role = null,
    ) {}
}
