<?php

namespace Modules\Accounting\Application\DTOs;

use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Enums\CashFlowCategory;

final readonly class UpdateAccountData
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $nameAr,
        public AccountType $type,
        public ?int $parentId,
        public bool $isPostable,
        public CashFlowCategory $cashFlowCategory,
    ) {}
}
