<?php

use Modules\Accounting\Application\Import\ExchangeRatesImporter;
use Modules\Accounting\Domain\Models\ExchangeRateEntry;

covers(ExchangeRatesImporter::class);

beforeEach(function () {
    bootstrapTenant();
    $this->importer = app(ExchangeRatesImporter::class);
});

it('previews without writing', function () {
    $report = $this->importer->run([2 => ['currency' => 'USD', 'date' => '2026-01-01', 'rate' => '48.5']], 'h', false);

    expect($report->hasErrors())->toBeFalse()->and(ExchangeRateEntry::count())->toBe(0);
});

it('saves rates and replaces an existing day', function () {
    $this->importer->run([2 => ['currency' => 'usd', 'date' => '2026-01-01', 'rate' => '48.5']], 'h', true);
    $report = $this->importer->run([
        2 => ['currency' => 'USD', 'date' => '2026-01-01', 'rate' => '49'],
        3 => ['currency' => 'SAR', 'date' => '2026-01-01', 'rate' => '12.9'],
    ], 'h', true);

    expect($report->created)->toBe(2)->and(ExchangeRateEntry::count())->toBe(2)
        ->and(ExchangeRateEntry::where('currency_code', 'USD')->first()->source)->toBe('import');
});

it('reports each kind of problem and writes nothing', function (array $row, string $key) {
    $report = $this->importer->run([2 => $row], 'h', true);

    expect($report->errors[0]['key'])->toBe($key)->and(ExchangeRateEntry::count())->toBe(0);
})->with([
    'base currency' => [['currency' => 'EGP', 'date' => '2026-01-01', 'rate' => '1'], 'accounting.errors.rate_base_currency'],
    'unknown currency' => [['currency' => 'ZZZ', 'date' => '2026-01-01', 'rate' => '1'], 'accounting.import.unknown_currency'],
    'bad rate' => [['currency' => 'USD', 'date' => '2026-01-01', 'rate' => 'abc'], 'accounting.import.bad_rate'],
    'zero rate' => [['currency' => 'USD', 'date' => '2026-01-01', 'rate' => '0'], 'accounting.import.bad_rate'],
    'bad date' => [['currency' => 'USD', 'date' => '01/02/2026', 'rate' => '1'], 'accounting.import.bad_date'],
    'impossible date' => [['currency' => 'USD', 'date' => '2026-02-31', 'rate' => '1'], 'accounting.import.bad_date'],
]);

it('rejects the same currency and day twice in one file', function () {
    $report = $this->importer->run([
        2 => ['currency' => 'USD', 'date' => '2026-01-01', 'rate' => '48'],
        3 => ['currency' => 'USD', 'date' => '2026-01-01', 'rate' => '49'],
    ], 'h', true);

    expect($report->errors[0]['key'])->toBe('accounting.import.duplicate_rate')->and($report->errors[0]['row'])->toBe(3);
});
