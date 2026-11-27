<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Shared\Domain\Exceptions\UnknownCurrency;
use Modules\Shared\Infrastructure\DatabaseCurrencyRegistry;

uses(RefreshDatabase::class);
covers(DatabaseCurrencyRegistry::class);

it('loads currencies with their decimals from the seeded table', function (string $code, int $decimals) {
    expect((new DatabaseCurrencyRegistry)->get($code)->decimals)->toBe($decimals);
})->with([['EGP', 2], ['SAR', 2], ['KWD', 3], ['JPY', 0], ['BHD', 3], ['USD', 2]]);

it('is case insensitive', function () {
    expect((new DatabaseCurrencyRegistry)->get('kwd')->code)->toBe('KWD');
});

it('knows which currencies exist', function () {
    $registry = new DatabaseCurrencyRegistry;

    expect($registry->exists('EGP'))->toBeTrue()->and($registry->exists('ZZZ'))->toBeFalse();
});

it('throws for an unknown currency', function () {
    (new DatabaseCurrencyRegistry)->get('ZZZ');
})->throws(UnknownCurrency::class);

it('memoises the table after the first read', function () {
    $registry = new DatabaseCurrencyRegistry;
    $registry->get('EGP');

    DB::flushQueryLog();
    DB::enableQueryLog();
    $registry->get('SAR');

    expect(DB::getQueryLog())->toBeEmpty();
});
