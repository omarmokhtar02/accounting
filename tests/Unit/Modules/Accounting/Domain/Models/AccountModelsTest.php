<?php

use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Enums\AccountStatus;
use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Enums\CashFlowCategory;
use Modules\Accounting\Domain\Models\Account;
use Modules\Accounting\Domain\Models\AccountMapping;

covers(Account::class, AccountMapping::class);

beforeEach(function () {
    ['company' => $this->company] = bootstrapTenant();
});

it('casts enums and booleans', function () {
    $account = makeAccount($this->company, '9601', AccountType::Revenue);

    expect($account->type)->toBe(AccountType::Revenue)
        ->and($account->status)->toBe(AccountStatus::Active)
        ->and($account->cash_flow_category)->toBe(CashFlowCategory::None)
        ->and($account->is_postable)->toBeTrue();
});

it('belongs to its company, parent and children', function () {
    $header = $this->company->accounts()->where('code', '11')->first();
    $child = $this->company->accounts()->where('code', '1101')->first();

    expect($child->company->id)->toBe($this->company->id)
        ->and($child->parent->id)->toBe($header->id)
        ->and($header->children->pluck('code'))->toContain('1101', '1102');
});

it('has journal lines', function () {
    openFiscalYear2026();
    ['user' => $user] = ['user' => auth()->user()];
    postedJournal($user);

    expect(accountByRole(AccountRole::Cash)->lines)->toHaveCount(1);
});

it('reports whether it is active', function () {
    $account = makeAccount($this->company, '9602');

    expect($account->isActive())->toBeTrue();

    $account->update(['status' => AccountStatus::Inactive]);

    expect($account->isActive())->toBeFalse();
});

it('shows the Arabic name in Arabic and falls back to English', function () {
    $account = makeAccount($this->company, '9603');
    $account->update(['name_ar' => 'حساب عربي']);

    app()->setLocale('ar');
    expect($account->localizedName())->toBe('حساب عربي');

    app()->setLocale('en');
    expect($account->localizedName())->toBe('Account 9603');

    $account->update(['name_ar' => null]);
    app()->setLocale('ar');
    expect($account->localizedName())->toBe('Account 9603');
});

it('is built by its factory', function () {
    $account = Account::factory()->create(['company_id' => $this->company->id]);

    expect($account->tenant_id)->toBe($this->company->tenant_id)->and($account->type)->toBeInstanceOf(AccountType::class);
});

it('maps a role to an account', function () {
    $mapping = AccountMapping::where('role', 'cash')->first();

    expect($mapping->role)->toBe(AccountRole::Cash)->and($mapping->account->code)->toBe('1213');
});

it('allows one account per role per company', function () {
    $other = makeAccount($this->company, '9604');

    AccountMapping::create(['company_id' => $this->company->id, 'role' => AccountRole::Cash, 'account_id' => $other->id]);
})->throws(UniqueConstraintViolationException::class);
