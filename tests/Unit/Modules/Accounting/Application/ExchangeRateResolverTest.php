<?php

use Carbon\CarbonImmutable;
use Modules\Accounting\Application\Commands\SaveExchangeRate;
use Modules\Accounting\Application\ExchangeRateResolver;
use Modules\Accounting\Domain\Exceptions\ExchangeRateError;
use Modules\Accounting\Domain\Models\ExchangeRateEntry;

covers(ExchangeRateResolver::class, ExchangeRateEntry::class);

beforeEach(function () {
    bootstrapTenant();
    $this->save = app(SaveExchangeRate::class);
    $this->resolver = app(ExchangeRateResolver::class);
});

it('uses the latest rate on or before the date', function () {
    $this->save->handle('USD', CarbonImmutable::parse('2026-03-01'), '48.5');
    $this->save->handle('USD', CarbonImmutable::parse('2026-03-10'), '49.25');

    expect($this->resolver->resolve('USD', CarbonImmutable::parse('2026-03-05'))->rate)->toBe('48.5')
        ->and($this->resolver->resolve('USD', CarbonImmutable::parse('2026-03-10'))->rate)->toBe('49.25')
        ->and($this->resolver->resolve('USD', CarbonImmutable::parse('2026-04-01'))->rate)->toBe('49.25');
});

it('returns the identity rate for the base currency', function () {
    expect($this->resolver->resolve('EGP', CarbonImmutable::parse('2026-03-05'))->rate)->toBe('1');
});

it('fails when no rate exists on or before the date', function () {
    $this->save->handle('USD', CarbonImmutable::parse('2026-03-10'), '49');

    $this->resolver->resolve('USD', CarbonImmutable::parse('2026-03-09'));
})->throws(ExchangeRateError::class);

it('builds a rate from an explicit string', function () {
    $rate = $this->resolver->make('USD', 'EGP', '50.1');

    expect($rate->from->code)->toBe('USD')->and($rate->to->code)->toBe('EGP')->and($rate->rate)->toBe('50.1');
});
