<?php

use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Enums\CashFlowCategory;
use Modules\Accounting\Domain\ValueObjects\AccountSeed;

covers(AccountSeed::class);

it('carries one template account', function () {
    $seed = new AccountSeed('1101', 'Cash', 'النقدية', AccountType::Asset, '11', true, CashFlowCategory::Cash, AccountRole::Cash);

    expect($seed->code)->toBe('1101')->and($seed->nameEn)->toBe('Cash')->and($seed->nameAr)->toBe('النقدية')
        ->and($seed->type)->toBe(AccountType::Asset)->and($seed->parentCode)->toBe('11')->and($seed->postable)->toBeTrue()
        ->and($seed->cashFlow)->toBe(CashFlowCategory::Cash)->and($seed->role)->toBe(AccountRole::Cash);
});

it('defaults to a postable account with no parent, role or cash-flow category', function () {
    $seed = new AccountSeed('1', 'Assets', 'الأصول', AccountType::Asset);

    expect($seed->parentCode)->toBeNull()->and($seed->postable)->toBeTrue()
        ->and($seed->cashFlow)->toBe(CashFlowCategory::None)->and($seed->role)->toBeNull();
});
