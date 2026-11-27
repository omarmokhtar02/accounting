<?php

namespace Modules\Kuwait\Domain;

use Modules\Localization\Domain\Contracts\CountryPack;
use Modules\Localization\Domain\CountryProfile;

/**
 * Kuwait: IFRS, no VAT today, KWD with 3 decimals, PIFSS / Zakat / NLST / KFAS.
 * Statutory computations (M3) and statements (M6) are added to this module; VAT can be switched on
 * later by changing the profile and adding the VAT roles to the chart, without touching core code.
 */
final class KuwaitCountryPack implements CountryPack
{
    public function profile(): CountryProfile
    {
        return new CountryProfile(
            code: 'KW',
            nameEn: 'Kuwait',
            nameAr: 'الكويت',
            currency: 'KWD',
            locale: 'ar',
            fiscalYearStartMonth: 1,
            accountingStandard: 'IFRS (as adopted in Kuwait)',
            vatEnabled: false,
            withholdingTax: false,
        );
    }

    public function chartOfAccounts(): array
    {
        return KuwaitChartOfAccounts::seeds();
    }

    /**
     * No VAT today. If Kuwait introduces one, add its codes here (or the platform admin adds rates) with no core change.
     */
    public function taxCodes(): array
    {
        return [];
    }

    public function version(): string
    {
        return '2026.1';
    }
}
