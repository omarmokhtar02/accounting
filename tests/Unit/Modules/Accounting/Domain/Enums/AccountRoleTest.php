<?php

use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Enums\AccountType;

covers(AccountRole::class);

it('knows exactly which roles are control accounts', function () {
    $control = collect(AccountRole::cases())->filter->isControl()->map->value->sort()->values()->all();

    expect($control)->toBe([
        'accounts_payable', 'accounts_receivable', 'accumulated_depreciation',
        'fixed_assets_cost', 'inventory', 'notes_payable', 'notes_receivable',
    ]);
});

it('expects a type for every role', function (AccountRole $role, AccountType $type) {
    expect($role->expectedType())->toBe($type);
})->with([
    [AccountRole::AccountsReceivable, AccountType::Asset], [AccountRole::NotesReceivable, AccountType::Asset],
    [AccountRole::Inventory, AccountType::Asset], [AccountRole::FixedAssetsCost, AccountType::Asset],
    [AccountRole::AccumulatedDepreciation, AccountType::Asset], [AccountRole::Cash, AccountType::Asset],
    [AccountRole::Bank, AccountType::Asset], [AccountRole::VatInput, AccountType::Asset],
    [AccountRole::WithholdingTaxReceivable, AccountType::Asset],
    [AccountRole::AccountsPayable, AccountType::Liability], [AccountRole::NotesPayable, AccountType::Liability],
    [AccountRole::VatOutput, AccountType::Liability], [AccountRole::WithholdingTaxPayable, AccountType::Liability],
    [AccountRole::RetainedEarnings, AccountType::Equity], [AccountRole::OpeningBalanceEquity, AccountType::Equity],
    [AccountRole::SalesRevenue, AccountType::Revenue],
    [AccountRole::RealizedFxGainLoss, AccountType::Expense], [AccountRole::UnrealizedFxGainLoss, AccountType::Expense],
    [AccountRole::RoundingDifference, AccountType::Expense], [AccountRole::CostOfGoodsSold, AccountType::Expense],
    [AccountRole::DepreciationExpense, AccountType::Expense], [AccountRole::BadDebtExpense, AccountType::Expense],
    [AccountRole::BankCharges, AccountType::Expense],
]);

it('covers every case in the type table above', function () {
    expect(AccountRole::cases())->toHaveCount(23);
});

it('has a label in English and Arabic for every role', function (string $locale) {
    app()->setLocale($locale);

    foreach (AccountRole::cases() as $role) {
        expect($role->label())->not->toStartWith('accounting.roles.');
    }
})->with(['en', 'ar']);
