<?php

use Modules\Accounting\Application\Commands\SeedChartOfAccounts;
use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Enums\CashFlowCategory;
use Modules\Accounting\Domain\Exceptions\ChartAlreadyExists;
use Modules\Accounting\Domain\Exceptions\InvalidChartTemplate;
use Modules\Accounting\Domain\Models\Account;
use Modules\Accounting\Domain\Models\AccountMapping;
use Modules\Accounting\Domain\ValueObjects\AccountSeed;
use Modules\Shared\Domain\Models\AuditLog;
use Modules\Tenancy\Domain\Models\Company;
use Modules\Tenancy\Infrastructure\TenantContext;

covers(SeedChartOfAccounts::class);

beforeEach(function () {
    ['company' => $existing] = bootstrapTenant();
    // A second, empty company in the same tenant: the target of the seeding.
    $this->company = Company::create(['name' => 'Empty', 'country_code' => 'EG', 'base_currency' => 'EGP']);
    app(TenantContext::class)->setCompany($this->company);
    $this->command = app(SeedChartOfAccounts::class);

    $this->seeds = [
        new AccountSeed('2', 'Liabilities', 'الخصوم', AccountType::Liability, null, false),
        new AccountSeed('1', 'Assets', 'الأصول', AccountType::Asset, null, false),
        new AccountSeed('11', 'Cash group', 'مجموعة النقدية', AccountType::Asset, '1', false),
        new AccountSeed('1101', 'Cash', 'النقدية', AccountType::Asset, '11', true, CashFlowCategory::Cash, AccountRole::Cash),
        new AccountSeed('2101', 'Suppliers', 'الموردون', AccountType::Liability, '2', true, CashFlowCategory::Operating, AccountRole::AccountsPayable),
    ];
});

it('creates accounts parents first, with names, flags and cash-flow category', function () {
    expect($this->command->handle($this->seeds))->toBe(5);

    $cash = Account::where('company_id', $this->company->id)->where('code', '1101')->first();

    expect(Account::where('company_id', $this->company->id)->count())->toBe(5)
        ->and($cash->parent->code)->toBe('11')
        ->and($cash->parent->parent->code)->toBe('1')
        ->and($cash->name_ar)->toBe('النقدية')
        ->and($cash->cash_flow_category)->toBe(CashFlowCategory::Cash)
        ->and($cash->is_postable)->toBeTrue()
        ->and(Account::where('company_id', $this->company->id)->where('code', '11')->first()->is_postable)->toBeFalse();
});

it('maps the roles declared by the template', function () {
    $this->command->handle($this->seeds);

    expect(AccountMapping::where('company_id', $this->company->id)->pluck('role')->map->value->sort()->values()->all())
        ->toBe(['accounts_payable', 'cash']);
});

it('audits the seeding', function () {
    $this->command->handle($this->seeds);

    expect(AuditLog::where('action', 'chart_seeded')->where('entity_id', $this->company->id)->first()->new_values)->toBe(['accounts' => 5]);
});

it('refuses a company that already has accounts', function () {
    $this->command->handle($this->seeds);

    $this->command->handle($this->seeds);
})->throws(ChartAlreadyExists::class);

it('validates the template and creates nothing when it is wrong', function () {
    $broken = [...$this->seeds, new AccountSeed('1101', 'Duplicate', 'مكرر', AccountType::Asset, '11')];

    expect(fn () => $this->command->handle($broken))->toThrow(InvalidChartTemplate::class)
        ->and(Account::where('company_id', $this->company->id)->count())->toBe(0);
});

it('enforces required roles', function () {
    $this->command->handle($this->seeds, [AccountRole::Cash, AccountRole::Bank]);
})->throws(InvalidChartTemplate::class);

it('rolls everything back when a later account fails', function () {
    Account::create(['company_id' => $this->company->id, 'code' => 'x', 'name' => 'x', 'type' => AccountType::Asset]);
    Account::where('company_id', $this->company->id)->delete();

    // Force a failure after the first rows by pre-creating a conflicting account in another transaction scope.
    expect(fn () => $this->command->handle([...$this->seeds, new AccountSeed('1101', 'Dup', 'مكرر', AccountType::Asset, '11')]))
        ->toThrow(InvalidChartTemplate::class);
    expect(Account::where('company_id', $this->company->id)->count())->toBe(0);
});
