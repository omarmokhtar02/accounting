<?php

use Carbon\CarbonImmutable;
use Modules\Accounting\Application\Commands\OpenFiscalYear;
use Modules\Accounting\Domain\Models\AccountingPeriod;
use Modules\Shared\Domain\Models\AuditLog;

covers(OpenFiscalYear::class);

beforeEach(function () {
    bootstrapTenant();
});

it('creates twelve consecutive monthly periods for a calendar year', function () {
    $year = app(OpenFiscalYear::class)->handle('2026', CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));
    $periods = $year->periods;

    expect($periods)->toHaveCount(12)
        ->and($periods->pluck('number')->all())->toBe(range(1, 12))
        ->and($periods->first()->starts_on->toDateString())->toBe('2026-01-01')
        ->and($periods->first()->ends_on->toDateString())->toBe('2026-01-31')
        ->and($periods[1]->ends_on->toDateString())->toBe('2026-02-28')
        ->and($periods->last()->ends_on->toDateString())->toBe('2026-12-31')
        ->and($periods->every(fn ($p) => $p->isOpen()))->toBeTrue();
});

it('leaves no gaps or overlaps between periods', function () {
    $year = app(OpenFiscalYear::class)->handle('FY', CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));

    $year->periods->each(function ($period, $i) use ($year) {
        if ($i > 0) {
            expect($period->starts_on->toDateString())->toBe($year->periods[$i - 1]->ends_on->addDay()->toDateString());
        }
    });
});

it('supports a fiscal year starting mid-year', function () {
    $year = app(OpenFiscalYear::class)->handle('2025/26', CarbonImmutable::parse('2025-07-01'), CarbonImmutable::parse('2026-06-30'));

    expect($year->periods)->toHaveCount(12)
        ->and($year->periods->first()->starts_on->toDateString())->toBe('2025-07-01')
        ->and($year->periods->last()->ends_on->toDateString())->toBe('2026-06-30');
});

it('handles a leap-year February', function () {
    $year = app(OpenFiscalYear::class)->handle('2028', CarbonImmutable::parse('2028-01-01'), CarbonImmutable::parse('2028-12-31'));

    expect($year->periods[1]->ends_on->toDateString())->toBe('2028-02-29');
});

it('ends the last period on the year end for a short year', function () {
    $year = app(OpenFiscalYear::class)->handle('Short', CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-03-15'));

    expect($year->periods)->toHaveCount(3)->and($year->periods->last()->ends_on->toDateString())->toBe('2026-03-15');
});

it('audits the opening and stays company scoped', function () {
    app(OpenFiscalYear::class)->handle('2026', CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));

    expect(AuditLog::where('entity_type', 'fiscal_year')->where('action', 'opened')->count())->toBe(1)
        ->and(AccountingPeriod::count())->toBe(12);
});
