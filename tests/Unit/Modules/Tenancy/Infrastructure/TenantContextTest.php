<?php

use Modules\Tenancy\Domain\Models\Company;
use Modules\Tenancy\Domain\Models\Tenant;
use Modules\Tenancy\Infrastructure\TenantContext;

covers(TenantContext::class);

beforeEach(function () {
    $this->context = new TenantContext;
});

it('starts empty', function () {
    expect($this->context->tenant())->toBeNull()->and($this->context->tenantId())->toBeNull()
        ->and($this->context->company())->toBeNull()->and($this->context->companyId())->toBeNull();
});

it('holds the tenant and company ids', function () {
    $company = Company::factory()->create();
    $this->context->setTenant($company->tenant);
    $this->context->setCompany($company);

    expect($this->context->tenantId())->toBe($company->tenant_id)->and($this->context->companyId())->toBe($company->id)
        ->and($this->context->tenant()->is($company->tenant))->toBeTrue();
});

it('drops the company when the tenant changes to another tenant', function () {
    $company = Company::factory()->create();
    $this->context->setTenant($company->tenant);
    $this->context->setCompany($company);

    $this->context->setTenant(Tenant::factory()->create());

    expect($this->context->company())->toBeNull();
});

it('keeps the company when the same tenant is set again', function () {
    $company = Company::factory()->create();
    $this->context->setTenant($company->tenant);
    $this->context->setCompany($company);
    $this->context->setTenant($company->tenant);

    expect($this->context->companyId())->toBe($company->id);
});

it('clears everything', function () {
    $company = Company::factory()->create();
    $this->context->setTenant($company->tenant);
    $this->context->setCompany($company);
    $this->context->clear();

    expect($this->context->tenant())->toBeNull()->and($this->context->company())->toBeNull();
});

it('is a scoped singleton so state never leaks between requests', function () {
    expect(app(TenantContext::class))->toBe(app(TenantContext::class));
});
