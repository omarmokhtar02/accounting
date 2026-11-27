<?php

use Carbon\CarbonImmutable;
use Modules\Accounting\Application\Commands\SaveExchangeRate;
use Modules\Accounting\Domain\Exceptions\ExchangeRateError;
use Modules\Accounting\Domain\Models\ExchangeRateEntry;
use Modules\Shared\Domain\Exceptions\InvalidMoneyAmount;
use Modules\Shared\Domain\Exceptions\UnknownCurrency;
use Modules\Shared\Domain\Models\AuditLog;

covers(SaveExchangeRate::class);

beforeEach(function () {
    bootstrapTenant();
    $this->save = app(SaveExchangeRate::class);
});

it('stores a rate and audits it', function () {
    $entry = $this->save->handle('usd', CarbonImmutable::parse('2026-03-01'), '48.5');

    expect($entry->currency_code)->toBe('USD')->and((float) $entry->rate)->toBe(48.5)
        ->and(AuditLog::where('entity_type', 'exchange_rate')->count())->toBe(1);
});

it('replaces the rate for the same currency and day', function () {
    $this->save->handle('USD', CarbonImmutable::parse('2026-03-01'), '48.5');
    $this->save->handle('USD', CarbonImmutable::parse('2026-03-01'), '49');

    expect(ExchangeRateEntry::count())->toBe(1)->and((float) ExchangeRateEntry::first()->rate)->toBe(49.0);
});

it('rejects a rate for the base currency', function () {
    $this->save->handle('EGP', CarbonImmutable::parse('2026-03-01'), '1');
})->throws(ExchangeRateError::class);

it('rejects invalid rates', function (string $rate) {
    $this->save->handle('USD', CarbonImmutable::parse('2026-03-01'), $rate);
})->with(['zero' => '0', 'negative' => '-1', 'text' => 'abc', 'too precise' => '1.12345678901'])->throws(InvalidMoneyAmount::class);

it('rejects an unknown currency', function () {
    $this->save->handle('ZZZ', CarbonImmutable::parse('2026-03-01'), '1');
})->throws(UnknownCurrency::class);
