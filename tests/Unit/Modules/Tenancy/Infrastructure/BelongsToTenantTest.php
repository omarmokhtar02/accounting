<?php

use Modules\Accounting\Domain\Models\Account;
use Modules\Tenancy\Domain\Models\Company;
use Modules\Tenancy\Infrastructure\BelongsToTenant;
use Modules\Tenancy\Infrastructure\Exceptions\InvalidTenantContext;
use Modules\Tenancy\Infrastructure\TenantContext;

covers(BelongsToTenant::class, InvalidTenantContext::class);

it('scopes every query to the current tenant', function () {
    ['company' => $a] = bootstrapTenant('a@example.com', 'A');
    $accountA = makeAccount($a, '9701');
    ['company' => $b] = bootstrapTenant('b@example.com', 'B');

    expect(Account::find($accountA->id))->toBeNull()
        ->and(Account::where('code', '9701')->count())->toBe(0)
        ->and(Company::count())->toBe(1);
});

it('fails closed: no tenant means no rows', function () {
    bootstrapTenant();
    app(TenantContext::class)->clear();

    expect(Account::count())->toBe(0)->and(Company::count())->toBe(0);
});

it('stamps the tenant on create', function () {
    ['company' => $company] = bootstrapTenant();

    expect(makeAccount($company, '9702')->tenant_id)->toBe($company->tenant_id);
});

it('refuses to create tenant data without a tenant context', function () {
    bootstrapTenant();
    app(TenantContext::class)->clear();

    Account::create(['company_id' => 1, 'code' => '1', 'name' => 'x', 'type' => 'asset']);
})->throws(InvalidTenantContext::class);

it('describes the missing context', function () {
    expect(InvalidTenantContext::missing()->getMessage())->toContain('tenant context');
});

it('exposes the tenant relation', function () {
    ['company' => $company] = bootstrapTenant();

    expect(makeAccount($company, '9703')->tenant->id)->toBe($company->tenant_id);
});

it('lets a global query escape the scope only explicitly', function () {
    bootstrapTenant('a@example.com', 'A');
    bootstrapTenant('b@example.com', 'B');

    expect(Company::withoutGlobalScopes()->count())->toBe(2)->and(Company::count())->toBe(1);
});
