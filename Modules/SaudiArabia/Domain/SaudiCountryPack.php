<?php

namespace Modules\SaudiArabia\Domain;

use Modules\Localization\Domain\Contracts\CountryPack;
use Modules\Localization\Domain\CountryProfile;
use Modules\Tax\Domain\Enums\TaxKind;
use Modules\Tax\Domain\ValueObjects\TaxCodeSeed;

/**
 * Saudi Arabia: IFRS as endorsed by SOCPA, VAT, Zakat, withholding tax, GOSI.
 * Tax rules (M3), statutory statements (M6) and ZATCA e-invoicing (M8) are added to this module.
 */
final class SaudiCountryPack implements CountryPack
{
    public function profile(): CountryProfile
    {
        return new CountryProfile(
            code: 'SA',
            nameEn: 'Saudi Arabia',
            nameAr: 'المملكة العربية السعودية',
            currency: 'SAR',
            locale: 'ar',
            fiscalYearStartMonth: 1,
            accountingStandard: 'IFRS as endorsed in Saudi Arabia (SOCPA)',
            vatEnabled: true,
            withholdingTax: true,
            hijriDisplay: true,
        );
    }

    public function chartOfAccounts(): array
    {
        return SaudiChartOfAccounts::seeds();
    }

    public function taxCodes(): array
    {
        return [
            // VAT: 5% from 2018-01-01, raised to 15% from 2020-07-01 (a real example of a rate that changed over time).
            new TaxCodeSeed('VAT_STD', TaxKind::Vat, 'VAT - standard rate', 'ضريبة القيمة المضافة - النسبة الأساسية', ['2018-01-01' => 500, '2020-07-01' => 1500]),
            new TaxCodeSeed('VAT_ZERO', TaxKind::Vat, 'VAT - zero rated', 'ضريبة القيمة المضافة - بنسبة صفر', ['2018-01-01' => 0]),
            new TaxCodeSeed('VAT_EXEMPT', TaxKind::Vat, 'VAT - exempt', 'ضريبة القيمة المضافة - معفاة', ['2018-01-01' => 0]),
            new TaxCodeSeed('WHT_NONRESIDENT', TaxKind::Withholding, 'Withholding tax - non-residents', 'ضريبة الاستقطاع - غير المقيمين'),
        ];
    }

    public function version(): string
    {
        return '2026.1';
    }
}
