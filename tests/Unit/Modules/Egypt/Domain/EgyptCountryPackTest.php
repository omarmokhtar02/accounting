<?php

use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Egypt\Domain\EgyptChartOfAccounts;
use Modules\Egypt\Domain\EgyptCountryPack;

covers(EgyptCountryPack::class, EgyptChartOfAccounts::class);

it('describes Egypt', function () {
    $profile = (new EgyptCountryPack)->profile();

    expect($profile->code)->toBe('EG')
        ->and($profile->currency)->toBe('EGP')
        ->and($profile->locale)->toBe('ar')
        ->and($profile->accountingStandard)->toContain('EAS')
        ->and($profile->vatEnabled)->toBeTrue()
        ->and($profile->withholdingTax)->toBeTrue()
        ->and($profile->hijriDisplay)->toBeFalse()
        ->and($profile->weekend)->toBe(['friday', 'saturday'])
        ->and($profile->fiscalYearStartMonth)->toBe(1);
});

it('carries the Egyptian taxes and statutory accounts', function () {
    $codes = collect((new EgyptCountryPack)->chartOfAccounts())->keyBy('code');

    expect($codes['1210']->role)->toBe(AccountRole::VatInput)
        ->and($codes['2105']->role)->toBe(AccountRole::VatOutput)
        ->and($codes['2107']->nameAr)->toBe('ضريبة الجدول مستحقة')
        ->and($codes['1211']->role)->toBe(AccountRole::WithholdingTaxReceivable)
        ->and($codes['2108']->role)->toBe(AccountRole::WithholdingTaxPayable)
        ->and($codes['2113']->nameAr)->toContain('الدمغة')
        ->and($codes['2110']->nameAr)->toContain('التأمينات')
        ->and($codes['3102']->nameAr)->toBe('الاحتياطي القانوني')
        ->and($codes['8101']->nameAr)->toContain('ضريبة الدخل');
});

it('does not carry Saudi or Kuwaiti statutory accounts', function () {
    $names = collect((new EgyptCountryPack)->chartOfAccounts())->pluck('nameEn')->implode('|');

    expect($names)->not->toContain('Zakat')->not->toContain('GOSI')->not->toContain('PIFSS')->not->toContain('NLST');
});

it('uses a stable pack version', function () {
    expect((new EgyptCountryPack)->version())->toBe('2026.1');
});
