<?php

use Modules\Shared\Domain\Contracts\CurrencyRegistry;
use Modules\Shared\Infrastructure\DatabaseCurrencyRegistry;
use Modules\Shared\Providers\SharedServiceProvider;

covers(SharedServiceProvider::class);

it('binds the currency registry to the database implementation', function () {
    expect(app(CurrencyRegistry::class))->toBeInstanceOf(DatabaseCurrencyRegistry::class);
});

it('scopes the registry so it is rebuilt per request', function () {
    $first = app(CurrencyRegistry::class);

    expect(app(CurrencyRegistry::class))->toBe($first);
});
