<?php

use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Shared\Domain\Currency;
use Modules\Shared\Domain\Money;
use Modules\Tax\Domain\Enums\TaxKind;
use Modules\Tax\Domain\Exceptions\InvalidTaxRateChange;
use Modules\Tax\Domain\Exceptions\TaxRateNotConfigured;
use Modules\Tax\Domain\Models\TaxCode;
use Modules\Tax\Domain\Models\TaxRateSchedule;
use Modules\Tax\Domain\Services\TaxCalculator;
use Modules\Tax\Domain\ValueObjects\TaxCodeSeed;
use Modules\Tax\Domain\ValueObjects\TaxRate;

covers(TaxCalculator::class, TaxRate::class, TaxCodeSeed::class, TaxKind::class, TaxRateNotConfigured::class,
    InvalidTaxRateChange::class, TaxRateSchedule::class, TaxCode::class);

describe('TaxRate', function () {
    it('holds basis points and the effective date', function () {
        $rate = new TaxRate(1400, '2017-07-01');

        expect($rate->basisPoints)->toBe(1400)->and($rate->effectiveFrom)->toBe('2017-07-01')->and($rate->isZero())->toBeFalse()
            ->and((new TaxRate(0, '2020-01-01'))->isZero())->toBeTrue();
    });

    it('rejects rates outside 0-100%', function (int $bp) {
        new TaxRate($bp, '2026-01-01');
    })->with([-1, 10001])->throws(InvalidArgumentException::class);

    it('shows a clean percentage', function (int $bp, string $expected) {
        expect((new TaxRate($bp, '2026-01-01'))->percent())->toBe($expected);
    })->with([[1400, '14'], [1500, '15'], [500, '5'], [250, '2.5'], [5, '0.05'], [0, '0'], [10000, '100'], [1425, '14.25'], [10, '0.1']]);
});

describe('TaxCalculator', function () {
    beforeEach(function () {
        $this->calc = new TaxCalculator;
        $this->egp = new Currency('EGP', 2);
        $this->kwd = new Currency('KWD', 3);
    });

    it('charges tax on a net amount', function (int $netMinor, int $bp, int $expectedTax) {
        $tax = $this->calc->onNet(new Money($netMinor, $this->egp), new TaxRate($bp, '2026-01-01'));

        expect($tax->minor)->toBe($expectedTax)->and($tax->currency->code)->toBe('EGP');
    })->with([
        'EG VAT on 100.00' => [10000, 1400, 1400],
        'KSA VAT on 100.00' => [10000, 1500, 1500],
        'zero rate' => [10000, 0, 0],
        'rounds half up: 0.5 minor' => [50, 1000, 5],
        'rounds .5 up' => [5, 1000, 1],
        'rounds below .5 down' => [4, 1000, 0],
        'large amount' => [123456789, 1400, 17283950],
        'negative (credit note)' => [-10000, 1400, -1400],
        'negative rounds away from zero' => [-5, 1000, -1],
    ]);

    it('respects the currency decimals', function () {
        expect($this->calc->onNet(new Money(100000, $this->kwd), new TaxRate(500, '2026-01-01'))->minor)->toBe(5000);
    });

    it('extracts the tax contained in a gross amount', function () {
        // 114.00 gross at 14% = 100.00 net + 14.00 tax
        $tax = $this->calc->insideGross(new Money(11400, $this->egp), new TaxRate(1400, '2026-01-01'));

        expect($tax->minor)->toBe(1400);
    });

    it('splits a gross amount so net plus tax is exactly the gross, always', function (int $gross, int $bp) {
        $parts = $this->calc->splitGross(new Money($gross, $this->egp), new TaxRate($bp, '2026-01-01'));

        expect($parts['net']->add($parts['tax'])->minor)->toBe($gross);
    })->with(array_map(fn ($g) => [$g, 1400], [1, 7, 99, 100, 1234, 99999, 123456]) + [['x' => [5000, 1500]]]);

    it('never uses floating point: a classic float trap stays exact', function () {
        // 0.1 + 0.2 style trap: 10.05 * 14% must be exactly 1.407 -> 1.41 (141 minor), not 1.4069999
        expect($this->calc->onNet(new Money(1005, $this->egp), new TaxRate(1400, '2026-01-01'))->minor)->toBe(141);
    });
});

describe('TaxCodeSeed', function () {
    it('accepts a code with dated default rates', function () {
        $seed = new TaxCodeSeed('VAT_STD', TaxKind::Vat, 'VAT', 'ضريبة', ['2018-01-01' => 500, '2020-07-01' => 1500]);

        expect($seed->code)->toBe('VAT_STD')->and($seed->kind)->toBe(TaxKind::Vat)->and($seed->defaultRates)->toBe(['2018-01-01' => 500, '2020-07-01' => 1500]);
    });

    it('allows a code with no default rate', function () {
        expect((new TaxCodeSeed('WHT', TaxKind::Withholding, 'W', 'خ'))->defaultRates)->toBe([]);
    });

    it('rejects bad codes, dates and rates', function (string $code, array $rates) {
        new TaxCodeSeed($code, TaxKind::Vat, 'x', 'x', $rates);
    })->with([
        'lowercase' => ['vat', []], 'too short' => ['V', []], 'spaces' => ['VAT STD', []],
        'bad date' => ['VAT', ['01/01/2020' => 100]], 'negative rate' => ['VAT', ['2020-01-01' => -1]], 'over 100%' => ['VAT', ['2020-01-01' => 10001]],
    ])->throws(InvalidArgumentException::class);
});

describe('TaxKind', function () {
    it('has a label in both languages', function (string $locale) {
        app()->setLocale($locale);

        foreach (TaxKind::cases() as $kind) {
            expect($kind->label())->not->toStartWith('tax.kinds.');
        }
    })->with(['en', 'ar']);
});

describe('tax exceptions', function () {
    it('translate into both languages with their details', function (callable $make) {
        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            $e = $make();

            expect($e->userMessage())->not->toStartWith('tax.errors.')->not->toBeEmpty();
        }
    })->with([
        [fn () => TaxRateNotConfigured::for('EG', 'WHT', '2026-01-01')],
        [fn () => InvalidTaxRateChange::inThePast()],
        [fn () => InvalidTaxRateChange::duplicateDate('2026-01-01')],
        [fn () => InvalidTaxRateChange::rateOutOfRange()],
        [fn () => InvalidTaxRateChange::alreadyInForce()],
        [fn () => InvalidTaxRateChange::unknownCode('EG', 'NOPE')],
    ]);

    it('names the country, code and date', function () {
        app()->setLocale('en');

        expect(TaxRateNotConfigured::for('EG', 'WHT', '2026-01-01')->userMessage())->toContain('EG')->toContain('WHT')->toContain('2026-01-01');
    });
});

describe('models', function () {
    it('casts the schedule and builds a TaxRate', function () {
        $row = TaxRateSchedule::create([
            'country_code' => 'EG', 'code' => 'VAT_STD', 'kind' => TaxKind::Vat, 'name_en' => 'VAT', 'name_ar' => 'ضريبة', 'rate_bp' => 1400, 'effective_from' => '2017-07-01',
        ]);

        expect($row->fresh()->kind)->toBe(TaxKind::Vat)->and($row->fresh()->toRate()->basisPoints)->toBe(1400)
            ->and($row->fresh()->toRate()->effectiveFrom)->toBe('2017-07-01');

        app()->setLocale('ar');
        expect($row->localizedName())->toBe('ضريبة');
    });

    it('allows one rate per country, code and date', function () {
        $attributes = ['country_code' => 'EG', 'code' => 'VAT_STD', 'kind' => 'vat', 'name_en' => 'V', 'name_ar' => 'ض', 'rate_bp' => 1400, 'effective_from' => '2017-07-01'];
        TaxRateSchedule::create($attributes);

        TaxRateSchedule::create($attributes);
    })->throws(UniqueConstraintViolationException::class);

    it('scopes company tax codes to the tenant and company', function () {
        ['company' => $a] = bootstrapTenant('a@example.com', 'A');
        $code = TaxCode::where('company_id', $a->id)->where('code', 'VAT_STD')->first();

        expect($code->kind)->toBe(TaxKind::Vat)->and($code->is_active)->toBeTrue();

        app()->setLocale('en');
        expect($code->localizedName())->toBe('VAT - standard rate');

        bootstrapTenant('b@example.com', 'B');

        expect(TaxCode::find($code->id))->toBeNull();
    });
});
