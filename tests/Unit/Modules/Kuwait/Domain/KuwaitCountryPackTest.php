<?php

use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Kuwait\Domain\KuwaitChartOfAccounts;
use Modules\Kuwait\Domain\KuwaitCountryPack;

covers(KuwaitCountryPack::class, KuwaitChartOfAccounts::class);

it('describes Kuwait without VAT', function () {
    $profile = (new KuwaitCountryPack)->profile();

    expect($profile->code)->toBe('KW')
        ->and($profile->currency)->toBe('KWD')
        ->and($profile->vatEnabled)->toBeFalse()
        ->and($profile->withholdingTax)->toBeFalse()
        ->and($profile->hijriDisplay)->toBeFalse();
});

it('has no VAT or withholding accounts', function () {
    $seeds = collect((new KuwaitCountryPack)->chartOfAccounts());
    $roles = $seeds->pluck('role')->filter()->all();

    expect($roles)->not->toContain(AccountRole::VatInput)
        ->not->toContain(AccountRole::VatOutput)
        ->not->toContain(AccountRole::WithholdingTaxPayable)
        ->and($seeds->pluck('nameEn')->implode('|'))->not->toContain('VAT');
});

it('carries PIFSS, Zakat, NLST, KFAS and staff indemnity', function () {
    $codes = collect((new KuwaitCountryPack)->chartOfAccounts())->keyBy('code');

    expect($codes['2105']->nameEn)->toContain('PIFSS')
        ->and($codes['2106']->nameAr)->toBe('الزكاة المستحقة')
        ->and($codes['2107']->nameEn)->toContain('NLST')
        ->and($codes['2108']->nameEn)->toContain('KFAS')
        ->and($codes['2201']->nameAr)->toBe('مخصص مكافأة نهاية الخدمة')
        ->and($codes['3102']->nameAr)->toBe('الاحتياطي الإجباري')
        ->and($codes['8101']->nameAr)->toBe('مصروف الزكاة')
        ->and($codes['8102']->nameAr)->toContain('دعم العمالة')
        ->and($codes['8103']->nameEn)->toContain('KFAS');
});

it('is ready for KWD three-decimal amounts', function () {
    expect((new KuwaitCountryPack)->profile()->currency)->toBe('KWD');
});
