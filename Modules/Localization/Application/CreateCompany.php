<?php

namespace Modules\Localization\Application;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Application\RecordAudit;
use Modules\Shared\Domain\Contracts\UsageLimits;
use Modules\Tenancy\Domain\Models\Company;
use Modules\Tenancy\Infrastructure\TenantContext;

final class CreateCompany
{
    public function __construct(
        private readonly CountryRegistry $registry,
        private readonly ApplyCountryPack $applyPack,
        private readonly TenantContext $context,
        private readonly RecordAudit $audit,
        private readonly UsageLimits $limits,
    ) {}

    /**
     * Creates a company in the current tenant for a country: its base currency comes from the
     * country pack, and the pack's chart of accounts and role mappings are applied. All or nothing.
     * Leaves the new company as the active one in the tenant context.
     */
    public function handle(string $name, ?string $nameAr, string $countryCode): Company
    {
        $profile = $this->registry->get($countryCode)->profile();
        $this->limits->assertCanAddCompany((int) $this->context->tenantId());

        return DB::transaction(function () use ($name, $nameAr, $profile): Company {
            $company = Company::create([
                'name' => $name,
                'name_ar' => $nameAr,
                'country_code' => $profile->code,
                'base_currency' => $profile->currency,
            ]);

            $this->context->setCompany($company);
            $this->applyPack->handle($company);

            $this->audit->handle('company', $company->id, 'created', null, ['country' => $profile->code, 'currency' => $profile->currency]);

            return $company->refresh();
        });
    }
}
