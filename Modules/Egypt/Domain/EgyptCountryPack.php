<?php

namespace Modules\Egypt\Domain;

use Modules\Localization\Domain\Contracts\CountryPack;
use Modules\Localization\Domain\CountryProfile;
use Modules\Tax\Domain\Enums\TaxKind;
use Modules\Tax\Domain\ValueObjects\TaxCodeSeed;

/**
 * Egypt: Egyptian Accounting Standards, VAT, table tax, withholding ("خصم وإضافة") and stamp duty.
 * Tax rules (M3), statutory statements (M6) and ETA e-invoicing (M8) are added to this module.
 */
final class EgyptCountryPack implements CountryPack
{
    public function profile(): CountryProfile
    {
        return new CountryProfile(
            code: 'EG',
            nameEn: 'Egypt',
            nameAr: 'مصر',
            currency: 'EGP',
            locale: 'ar',
            fiscalYearStartMonth: 1,
            accountingStandard: 'Egyptian Accounting Standards (EAS)',
            vatEnabled: true,
            withholdingTax: true,
        );
    }

    public function chartOfAccounts(): array
    {
        return EgyptChartOfAccounts::seeds();
    }

    public function taxCodes(): array
    {
        return [
            // VAT: 14% since 2017-07-01. Later changes are added by the platform admin, not in code.
            new TaxCodeSeed('VAT_STD', TaxKind::Vat, 'VAT - standard rate', 'ضريبة القيمة المضافة - النسبة الأساسية', ['2017-07-01' => 1400]),
            new TaxCodeSeed('VAT_ZERO', TaxKind::Vat, 'VAT - zero rated', 'ضريبة القيمة المضافة - سلع بنسبة صفر', ['2017-07-01' => 0]),
            new TaxCodeSeed('VAT_EXEMPT', TaxKind::Vat, 'VAT - exempt', 'ضريبة القيمة المضافة - معفاة', ['2017-07-01' => 0]),
            // No default rate: the admin sets the current rate from the law before use.
            new TaxCodeSeed('WHT', TaxKind::Withholding, 'Withholding and addition tax', 'ضريبة الخصم والإضافة'),
            new TaxCodeSeed('TABLE_TAX', TaxKind::TableTax, 'Table tax', 'ضريبة الجدول'),
            new TaxCodeSeed('STAMP_DUTY', TaxKind::StampDuty, 'Stamp duty', 'ضريبة الدمغة'),
        ];
    }

    public function version(): string
    {
        return '2026.1';
    }
}
