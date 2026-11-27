<?php

use Carbon\CarbonImmutable;
use Modules\Localization\Application\ApplyTaxCodes;
use Modules\Localization\Application\SyncTaxDefaults;
use Modules\Localization\Presentation\Console\SyncTaxDefaultsCommand;
use Modules\Tax\Application\RemoveScheduledTaxRate;
use Modules\Tax\Application\ScheduleTaxRate;
use Modules\Tax\Application\TaxRateResolver;
use Modules\Tax\Domain\Enums\TaxKind;
use Modules\Tax\Domain\Exceptions\InvalidTaxRateChange;
use Modules\Tax\Domain\Exceptions\TaxRateNotConfigured;
use Modules\Tax\Domain\Models\TaxCode;
use Modules\Tax\Domain\Models\TaxRateSchedule;
use Modules\Tax\Domain\ValueObjects\TaxCodeSeed;
use Modules\Tax\Providers\TaxServiceProvider;

covers(TaxRateResolver::class, ScheduleTaxRate::class, RemoveScheduledTaxRate::class, TaxServiceProvider::class,
    SyncTaxDefaults::class, ApplyTaxCodes::class, SyncTaxDefaultsCommand::class);

function vatSeed(): TaxCodeSeed
{
    return new TaxCodeSeed('VAT_STD', TaxKind::Vat, 'VAT - standard rate', 'ضريبة القيمة المضافة - النسبة الأساسية');
}

describe('defaults from the country packs', function () {
    it('are inserted once and never overwrite admin edits', function () {
        $first = app(SyncTaxDefaults::class)->handle();

        // Egypt: VAT_STD, VAT_ZERO, VAT_EXEMPT (1 each); KSA: VAT_STD x2, VAT_ZERO, VAT_EXEMPT; Kuwait: none
        expect($first)->toBe(3 + 4)->and(TaxRateSchedule::count())->toBe(7);

        TaxRateSchedule::where('country_code', 'EG')->where('code', 'VAT_STD')->update(['rate_bp' => 1600]);

        expect(app(SyncTaxDefaults::class)->handle())->toBe(0)
            ->and(TaxRateSchedule::where('country_code', 'EG')->where('code', 'VAT_STD')->value('rate_bp'))->toBe(1600);
    });

    it('is available as an artisan command', function () {
        $this->artisan('tax:sync-defaults')->expectsOutput('Inserted 7 default tax rate(s).')->assertSuccessful();
        $this->artisan('tax:sync-defaults')->expectsOutput('Inserted 0 default tax rate(s).')->assertSuccessful();
    });

    it('give Saudi VAT its real history: 5% then 15%', function () {
        app(SyncTaxDefaults::class)->handle();
        $resolver = app(TaxRateResolver::class);

        expect($resolver->rateFor('SA', 'VAT_STD', CarbonImmutable::parse('2019-06-01'))->basisPoints)->toBe(500)
            ->and($resolver->rateFor('SA', 'VAT_STD', CarbonImmutable::parse('2020-06-30'))->basisPoints)->toBe(500)
            ->and($resolver->rateFor('SA', 'VAT_STD', CarbonImmutable::parse('2020-07-01'))->basisPoints)->toBe(1500)
            ->and($resolver->rateFor('SA', 'VAT_STD', CarbonImmutable::parse('2026-03-15'))->basisPoints)->toBe(1500)
            ->and($resolver->rateFor('EG', 'VAT_STD', CarbonImmutable::parse('2026-03-15'))->basisPoints)->toBe(1400);
    });

    it('create no VAT for Kuwait', function () {
        app(SyncTaxDefaults::class)->handle();

        expect(TaxRateSchedule::where('country_code', 'KW')->count())->toBe(0);
    });
});

describe('company tax codes', function () {
    it('come from the country pack when the company is created', function (string $country, array $codes) {
        ['company' => $company] = bootstrapTenant("tc-{$country}@example.com", "TC {$country}", $country);

        expect(TaxCode::where('company_id', $company->id)->pluck('code')->sort()->values()->all())->toBe($codes);
    })->with([
        ['EG', ['STAMP_DUTY', 'TABLE_TAX', 'VAT_EXEMPT', 'VAT_STD', 'VAT_ZERO', 'WHT']],
        ['SA', ['VAT_EXEMPT', 'VAT_STD', 'VAT_ZERO', 'WHT_NONRESIDENT']],
        ['KW', []],
    ]);

    it('are created idempotently', function () {
        ['company' => $company] = bootstrapTenant();
        $apply = app(ApplyTaxCodes::class);

        expect($apply->handle([vatSeed()]))->toBe(0)
            ->and($apply->handle([new TaxCodeSeed('NEW_TAX', TaxKind::Other, 'New', 'جديدة')]))->toBe(1)
            ->and(TaxCode::where('company_id', $company->id)->where('code', 'NEW_TAX')->count())->toBe(1);
    });
});

describe('TaxRateResolver', function () {
    beforeEach(function () {
        $this->resolver = app(TaxRateResolver::class);
        $this->schedule = app(ScheduleTaxRate::class);
    });

    function addRate(string $date, int $bp, string $country = 'EG', string $code = 'VAT_STD'): TaxRateSchedule
    {
        return TaxRateSchedule::create([
            'country_code' => $country, 'code' => $code, 'kind' => 'vat', 'name_en' => 'VAT', 'name_ar' => 'ض', 'rate_bp' => $bp, 'effective_from' => $date,
        ]);
    }

    it('picks the rate in force on the date: the latest effective_from not after it', function () {
        addRate('2017-07-01', 1400);
        addRate('2030-01-01', 1500);

        expect($this->resolver->rateFor('EG', 'VAT_STD', CarbonImmutable::parse('2017-07-01'))->basisPoints)->toBe(1400)
            ->and($this->resolver->rateFor('EG', 'VAT_STD', CarbonImmutable::parse('2029-12-31'))->basisPoints)->toBe(1400)
            ->and($this->resolver->rateFor('EG', 'VAT_STD', CarbonImmutable::parse('2030-01-01'))->basisPoints)->toBe(1500)
            ->and($this->resolver->rateFor('eg', 'VAT_STD', CarbonImmutable::parse('2031-01-01'))->basisPoints)->toBe(1500);
    });

    it('fails clearly before the first rate and for unknown codes', function () {
        addRate('2017-07-01', 1400);

        expect(fn () => $this->resolver->rateFor('EG', 'VAT_STD', CarbonImmutable::parse('2017-06-30')))->toThrow(TaxRateNotConfigured::class)
            ->and(fn () => $this->resolver->rateFor('EG', 'NOPE', CarbonImmutable::parse('2026-01-01')))->toThrow(TaxRateNotConfigured::class)
            ->and(fn () => $this->resolver->rateFor('KW', 'VAT_STD', CarbonImmutable::parse('2026-01-01')))->toThrow(TaxRateNotConfigured::class);
    });

    it('reports whether a rate exists', function () {
        addRate('2017-07-01', 1400);

        expect($this->resolver->hasRate('EG', 'VAT_STD', CarbonImmutable::parse('2026-01-01')))->toBeTrue()
            ->and($this->resolver->hasRate('EG', 'WHT', CarbonImmutable::parse('2026-01-01')))->toBeFalse();
    });

    it('finds the next scheduled change and the full history', function () {
        addRate('2017-07-01', 1400);
        addRate('2030-01-01', 1500);
        addRate('2032-01-01', 1600);

        $next = $this->resolver->nextChange('EG', 'VAT_STD', CarbonImmutable::parse('2026-01-01'));

        expect($next->effective_from->toDateString())->toBe('2030-01-01')
            ->and($this->resolver->nextChange('EG', 'VAT_STD', CarbonImmutable::parse('2033-01-01')))->toBeNull()
            ->and($this->resolver->history('EG', 'VAT_STD')->pluck('rate_bp')->all())->toBe([1600, 1500, 1400]);
    });

    it('sees a new rate immediately after it is scheduled (no stale cache)', function () {
        addRate('2017-07-01', 1400);
        $this->resolver->rateFor('EG', 'VAT_STD', now());

        $this->schedule->handle('EG', vatSeed(), 1500, now());

        expect($this->resolver->rateFor('EG', 'VAT_STD', now())->basisPoints)->toBe(1500);
    });

    it('is one shared instance per request', function () {
        expect(app(TaxRateResolver::class))->toBe(app(TaxRateResolver::class));
    });
});

describe('ScheduleTaxRate: rates are dynamic data', function () {
    beforeEach(function () {
        $this->schedule = app(ScheduleTaxRate::class);
    });

    it('applies a change from today and keeps the older rate for older dates', function () {
        addRate('2017-07-01', 1400);

        $row = $this->schedule->handle('EG', vatSeed(), 1500, now(), 'Decree 123', 9);

        expect($row->rate_bp)->toBe(1500)->and($row->note)->toBe('Decree 123')->and($row->created_by)->toBe(9)
            ->and(app(TaxRateResolver::class)->rateFor('EG', 'VAT_STD', now())->basisPoints)->toBe(1500)
            ->and(app(TaxRateResolver::class)->rateFor('EG', 'VAT_STD', CarbonImmutable::parse('2020-01-01'))->basisPoints)->toBe(1400);
    });

    it('schedules a future change that switches over by itself on its date', function () {
        addRate('2017-07-01', 1400);
        $date = now()->addDays(30);

        $this->schedule->handle('EG', vatSeed(), 1600, $date);
        $resolver = app(TaxRateResolver::class);

        expect($resolver->rateFor('EG', 'VAT_STD', now())->basisPoints)->toBe(1400)
            ->and($resolver->rateFor('EG', 'VAT_STD', $date->copy()->subDay())->basisPoints)->toBe(1400)
            ->and($resolver->rateFor('EG', 'VAT_STD', $date)->basisPoints)->toBe(1600);
    });

    it('can set a first rate for a code that had no default (withholding)', function () {
        $wht = new TaxCodeSeed('WHT', TaxKind::Withholding, 'Withholding and addition tax', 'ضريبة الخصم والإضافة');

        $row = $this->schedule->handle('EG', $wht, 300, now());

        expect($row->kind)->toBe(TaxKind::Withholding)->and($row->name_ar)->toBe('ضريبة الخصم والإضافة')
            ->and(app(TaxRateResolver::class)->rateFor('EG', 'WHT', now())->basisPoints)->toBe(300);
    });

    it('can introduce a tax where none existed, such as VAT in Kuwait, with no code change', function () {
        $this->schedule->handle('KW', vatSeed(), 500, now()->addMonth());

        expect(app(TaxRateResolver::class)->rateFor('KW', 'VAT_STD', now()->addMonth())->basisPoints)->toBe(500)
            ->and(app(TaxRateResolver::class)->hasRate('KW', 'VAT_STD', now()))->toBeFalse();
    });

    it('refuses past dates, duplicate dates and out-of-range rates', function () {
        $this->schedule->handle('EG', vatSeed(), 1400, now());

        expect(fn () => $this->schedule->handle('EG', vatSeed(), 1500, now()->subDay()))->toThrow(InvalidTaxRateChange::class)
            ->and(fn () => $this->schedule->handle('EG', vatSeed(), 1500, now()))->toThrow(InvalidTaxRateChange::class)
            ->and(fn () => $this->schedule->handle('EG', vatSeed(), 10001, now()->addDay()))->toThrow(InvalidTaxRateChange::class)
            ->and(fn () => $this->schedule->handle('EG', vatSeed(), -1, now()->addDay()))->toThrow(InvalidTaxRateChange::class);

        expect(TaxRateSchedule::count())->toBe(1);
    });

    it('allows the same date for different countries or codes', function () {
        $this->schedule->handle('EG', vatSeed(), 1400, now());
        $this->schedule->handle('SA', vatSeed(), 1500, now());
        $this->schedule->handle('EG', new TaxCodeSeed('VAT_ZERO', TaxKind::Vat, 'Zero', 'صفر'), 0, now());

        expect(TaxRateSchedule::count())->toBe(3);
    });

    it('allows a zero rate (zero-rated or exempt) and the 100% boundary', function () {
        $this->schedule->handle('EG', vatSeed(), 0, now());
        $this->schedule->handle('EG', vatSeed(), 10000, now()->addDay());

        expect(TaxRateSchedule::pluck('rate_bp')->sort()->values()->all())->toBe([0, 10000]);
    });
});

describe('RemoveScheduledTaxRate', function () {
    it('cancels a future change but never a rate already in force', function () {
        $future = addRate(now()->addDays(10)->toDateString(), 1600);
        $today = addRate(now()->toDateString(), 1500);
        $past = addRate('2017-07-01', 1400);
        $remove = app(RemoveScheduledTaxRate::class);

        $remove->handle($future);

        expect(TaxRateSchedule::find($future->id))->toBeNull()
            ->and(fn () => $remove->handle($today))->toThrow(InvalidTaxRateChange::class)
            ->and(fn () => $remove->handle($past))->toThrow(InvalidTaxRateChange::class)
            ->and(TaxRateSchedule::count())->toBe(2);
    });

    it('lets the resolver forget the removed rate immediately', function () {
        addRate('2017-07-01', 1400);
        $future = addRate(now()->addDays(10)->toDateString(), 1600);
        $resolver = app(TaxRateResolver::class);

        expect($resolver->nextChange('EG', 'VAT_STD', now())?->id)->toBe($future->id);

        app(RemoveScheduledTaxRate::class)->handle($future);

        expect($resolver->nextChange('EG', 'VAT_STD', now()))->toBeNull();
    });
});

describe('TaxServiceProvider', function () {
    it('registers the tax views and the tenant tax route', function () {
        expect(view()->exists('tax::codes.index'))->toBeTrue()->and(route('tax.codes.index'))->toContain('/tax');
    });
});
