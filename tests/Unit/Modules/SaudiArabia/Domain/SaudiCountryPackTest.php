<?php

use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\SaudiArabia\Domain\SaudiChartOfAccounts;
use Modules\SaudiArabia\Domain\SaudiCountryPack;

covers(SaudiCountryPack::class, SaudiChartOfAccounts::class);

it('describes Saudi Arabia', function () {
    $profile = (new SaudiCountryPack)->profile();

    expect($profile->code)->toBe('SA')
        ->and($profile->currency)->toBe('SAR')
        ->and($profile->accountingStandard)->toContain('SOCPA')
        ->and($profile->vatEnabled)->toBeTrue()
        ->and($profile->withholdingTax)->toBeTrue()
        ->and($profile->hijriDisplay)->toBeTrue()
        ->and($profile->weekend)->toBe(['friday', 'saturday']);
});

it('carries VAT, Zakat, GOSI, end-of-service and IFRS 16 accounts', function () {
    $codes = collect((new SaudiCountryPack)->chartOfAccounts())->keyBy('code');

    expect($codes['1210']->role)->toBe(AccountRole::VatInput)
        ->and($codes['2105']->role)->toBe(AccountRole::VatOutput)
        ->and($codes['2106']->nameEn)->toContain('ZATCA')
        ->and($codes['2107']->nameAr)->toBe('الزكاة المستحقة')
        ->and($codes['8101']->nameAr)->toBe('مصروف الزكاة')
        ->and($codes['2109']->role)->toBe(AccountRole::WithholdingTaxPayable)
        ->and($codes['2110']->nameEn)->toContain('GOSI')
        ->and($codes['2201']->nameAr)->toBe('مخصص مكافأة نهاية الخدمة')
        ->and($codes['1110']->nameEn)->toContain('IFRS 16')
        ->and($codes['3102']->nameAr)->toBe('الاحتياطي النظامي');
});

it('does not carry Egyptian table tax or Kuwaiti levies', function () {
    $names = collect((new SaudiCountryPack)->chartOfAccounts())->pluck('nameEn')->implode('|');

    expect($names)->not->toContain('Table tax')->not->toContain('PIFSS')->not->toContain('NLST')->not->toContain('KFAS');
});
