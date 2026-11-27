<?php

use Modules\Shared\Domain\Currency;
use Modules\Shared\Domain\Exceptions\InvalidMoneyAmount;
use Modules\Shared\Domain\ExchangeRate;

covers(ExchangeRate::class);

beforeEach(function () {
    $this->egp = new Currency('EGP', 2);
    $this->usd = new Currency('USD', 2);
});

it('accepts valid rates', function (string $rate) {
    expect((new ExchangeRate($this->egp, $this->usd, $rate))->rate)->toBe($rate);
})->with(['1', '0.0063', '150.5', '48.1234567891']);

it('rejects invalid rates', function (string $rate) {
    new ExchangeRate($this->egp, $this->usd, $rate);
})->with(['0', '0.0', '-1', 'abc', '', '1.12345678901', '1,5'])->throws(InvalidMoneyAmount::class);

it('builds an identity rate', function () {
    $rate = ExchangeRate::identity($this->egp);

    expect($rate->rate)->toBe('1')->and($rate->from->code)->toBe('EGP')->and($rate->to->code)->toBe('EGP');
});

it('inverts a rate and swaps the currencies', function () {
    $inverse = (new ExchangeRate($this->egp, $this->usd, '0.02'))->inverse();

    expect($inverse->from->code)->toBe('USD')->and($inverse->to->code)->toBe('EGP')->and($inverse->rate)->toBe('50');
});

it('keeps a positive rate when the inverse underflows', function () {
    $inverse = (new ExchangeRate($this->egp, $this->usd, '99999999999'))->inverse();

    expect(bccomp($inverse->rate, '0', 10))->toBe(1);
});

it('strips only insignificant trailing zeros', function (string $input, string $expected) {
    expect(ExchangeRate::plain($input))->toBe($expected);
})->with([['50.5000000000', '50.5'], ['50.0000000000', '50'], ['50', '50'], ['100', '100'], ['0.0500', '0.05']]);
