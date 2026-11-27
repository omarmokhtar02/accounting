<?php

namespace Modules\Localization\Application;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Application\Commands\SeedChartOfAccounts;
use Modules\Shared\Application\RecordAudit;
use Modules\Tenancy\Domain\Models\Company;
use Modules\Tenancy\Infrastructure\TenantContext;

final class ApplyCountryPack
{
    public function __construct(
        private readonly CountryRegistry $registry,
        private readonly SeedChartOfAccounts $seedChart,
        private readonly TenantContext $context,
        private readonly RecordAudit $audit,
        private readonly ApplyTaxCodes $applyTaxCodes,
        private readonly SyncTaxDefaults $syncTaxDefaults,
    ) {}

    /**
     * Applies the company's country pack: its chart of accounts, role mappings and settings.
     * The company must be the active one (tenant context) and must not have accounts yet.
     */
    public function handle(Company $company): void
    {
        $pack = $this->registry->get($company->country_code);
        $profile = $pack->profile();

        DB::transaction(function () use ($company, $pack, $profile): void {
            $this->context->setCompany($company);
            $this->seedChart->handle($pack->chartOfAccounts(), $profile->requiredRoles());

            $this->syncTaxDefaults->handle();
            $this->applyTaxCodes->handle($pack->taxCodes());

            $company->update(['country_pack_version' => $pack->version()]);

            $this->audit->handle('company', $company->id, 'country_pack_applied', null, [
                'country' => $profile->code,
                'version' => $pack->version(),
            ]);
        });
    }
}
