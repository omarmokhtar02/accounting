<?php

use Modules\Shared\Domain\Currency;

covers(Currency::class);

it('exposes its code and decimals', function () {
    $kwd = new Currency('KWD', 3);

    expect($kwd->code)->toBe('KWD')->and($kwd->decimals)->toBe(3);
});

it('computes the minor-unit factor from its decimals', function (string $code, int $decimals, string $factor) {
    expect((new Currency($code, $decimals))->factor())->toBe($factor);
})->with([['JPY', 0, '1'], ['EGP', 2, '100'], ['KWD', 3, '1000'], ['XXX', 4, '10000']]);

it('rejects malformed codes', function (string $code) {
    new Currency($code, 2);
})->with(['egp', 'EG', 'EGPP', 'E1P', ''])->throws(InvalidArgumentException::class);

it('rejects unsupported decimals', function (int $decimals) {
    new Currency('EGP', $decimals);
})->with([-1, 5])->throws(InvalidArgumentException::class);

it('compares by code', function () {
    expect((new Currency('EGP', 2))->equals(new Currency('EGP', 2)))->toBeTrue()
        ->and((new Currency('EGP', 2))->equals(new Currency('SAR', 2)))->toBeFalse();
});
