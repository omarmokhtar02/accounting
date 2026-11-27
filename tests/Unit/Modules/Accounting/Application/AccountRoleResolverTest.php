<?php

use Modules\Accounting\Application\AccountRoleResolver;
use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Exceptions\AccountRoleError;
use Modules\Accounting\Domain\Models\AccountMapping;
use Modules\Tenancy\Infrastructure\Exceptions\InvalidTenantContext;
use Modules\Tenancy\Infrastructure\TenantContext;

covers(AccountRoleResolver::class);

beforeEach(function () {
    bootstrapTenant();
    $this->resolver = app(AccountRoleResolver::class);
});

it('resolves the account that plays a role', function () {
    $account = $this->resolver->account(AccountRole::AccountsReceivable);

    expect($account->code)->toBe('1203')
        ->and($this->resolver->accountId(AccountRole::AccountsReceivable))->toBe($account->id);
});

it('throws when a role is not mapped', function () {
    AccountMapping::where('role', AccountRole::VatInput->value)->delete();
    $this->resolver->forget();

    $this->resolver->accountId(AccountRole::VatInput);
})->throws(AccountRoleError::class);

it('returns only control accounts', function () {
    $control = $this->resolver->controlAccountIds();

    expect(array_keys($control))->toContain('accounts_receivable', 'accounts_payable', 'inventory', 'fixed_assets_cost', 'notes_receivable')
        ->not->toContain('cash')->not->toContain('sales_revenue')->not->toContain('vat_input');
});

it('caches mappings until forget() is called', function () {
    $first = $this->resolver->accountId(AccountRole::Cash);
    $other = makeAccount(app(TenantContext::class)->company(), '9100');
    AccountMapping::where('role', AccountRole::Cash->value)->update(['account_id' => $other->id]);

    expect($this->resolver->accountId(AccountRole::Cash))->toBe($first);

    $this->resolver->forget();

    expect($this->resolver->accountId(AccountRole::Cash))->toBe($other->id);
});

it('needs a company in the tenant context', function () {
    app(TenantContext::class)->clear();

    $this->resolver->accountId(AccountRole::Cash);
})->throws(InvalidTenantContext::class);

it('needs a company for control accounts too', function () {
    app(TenantContext::class)->clear();

    $this->resolver->controlAccountIds();
})->throws(InvalidTenantContext::class);
