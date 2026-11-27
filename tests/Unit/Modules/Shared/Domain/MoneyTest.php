<?php

use Modules\Shared\Domain\Currency;
use Modules\Shared\Domain\Exceptions\CurrencyMismatch;
use Modules\Shared\Domain\Exceptions\InvalidMoneyAmount;
use Modules\Shared\Domain\ExchangeRate;
use Modules\Shared\Domain\Money;

covers(Money::class);

beforeEach(function () {
    $this->egp = new Currency('EGP', 2);
    $this->kwd = new Currency('KWD', 3);
    $this->jpy = new Currency('JPY', 0);
    $this->usd = new Currency('USD', 2);
});

it('parses decimal strings per the currency decimals', function (string $input, string $code, int $decimals, int $minor) {
    expect(Money::fromDecimal($input, new Currency($code, $decimals))->minor)->toBe($minor);
})->with([
    ['10', 'EGP', 2, 1000], ['10.5', 'EGP', 2, 1050], ['0.07', 'EGP', 2, 7], ['1,234.56', 'EGP', 2, 123456],
    ['1.234', 'KWD', 3, 1234], ['0.005', 'KWD', 3, 5], ['2.5', 'KWD', 3, 2500], ['500', 'JPY', 0, 500],
    ['-0.50', 'EGP', 2, -50], ['-12', 'JPY', 0, -12], ['  7.00 ', 'EGP', 2, 700], ['0', 'EGP', 2, 0],
]);

it('refuses to silently round extra decimals', function (string $input, string $code, int $decimals) {
    Money::fromDecimal($input, new Currency($code, $decimals));
})->with([['10.123', 'EGP', 2], ['1.2345', 'KWD', 3], ['5.5', 'JPY', 0]])->throws(InvalidMoneyAmount::class);

it('refuses non numeric input', function (string $input) {
    Money::fromDecimal($input, $this->egp);
})->with(['abc', '', '.5', '1.2.3', '--1', '1e3', '12a'])->throws(InvalidMoneyAmount::class);

it('renders plain decimals with exactly the currency decimals', function (int $minor, string $code, int $decimals, string $expected) {
    expect((new Money($minor, new Currency($code, $decimals)))->toDecimal())->toBe($expected);
})->with([
    [7, 'EGP', 2, '0.07'], [123456, 'EGP', 2, '1234.56'], [5, 'KWD', 3, '0.005'], [1500, 'KWD', 3, '1.500'],
    [0, 'JPY', 0, '0'], [1234, 'JPY', 0, '1234'], [-50, 'EGP', 2, '-0.50'], [0, 'EGP', 2, '0.00'],
]);

it('formats with thousands separators', function (int $minor, string $code, int $decimals, string $expected) {
    expect((new Money($minor, new Currency($code, $decimals)))->format())->toBe($expected);
})->with([
    [123456, 'EGP', 2, '1,234.56'], [1234567, 'KWD', 3, '1,234.567'], [1234567, 'JPY', 0, '1,234,567'],
    [-50, 'EGP', 2, '-0.50'], [100000000, 'EGP', 2, '1,000,000.00'],
]);

it('round-trips parse and render', function (string $value) {
    expect(Money::fromDecimal($value, $this->kwd)->toDecimal())->toBe($value);
})->with(['0.000', '1.500', '123456.789']);

it('adds and subtracts in the same currency', function () {
    $a = new Money(1000, $this->egp);
    $b = new Money(250, $this->egp);

    expect($a->add($b)->minor)->toBe(1250)->and($a->subtract($b)->minor)->toBe(750)->and($b->subtract($a)->minor)->toBe(-750);
});

it('refuses arithmetic across currencies', function (string $op) {
    (new Money(100, $this->egp))->{$op}(new Money(100, $this->usd));
})->with(['add', 'subtract', 'compare'])->throws(CurrencyMismatch::class);

it('negates and takes the absolute value', function () {
    $m = new Money(-300, $this->egp);

    expect($m->negate()->minor)->toBe(300)->and($m->abs()->minor)->toBe(300)->and((new Money(300, $this->egp))->abs()->minor)->toBe(300);
});

it('reports sign and zero', function () {
    expect((new Money(0, $this->egp))->isZero())->toBeTrue()
        ->and((new Money(1, $this->egp))->isPositive())->toBeTrue()
        ->and((new Money(-1, $this->egp))->isNegative())->toBeTrue()
        ->and((new Money(1, $this->egp))->isNegative())->toBeFalse()
        ->and((new Money(0, $this->egp))->isPositive())->toBeFalse();
});

it('compares amounts', function () {
    $a = new Money(100, $this->egp);

    expect($a->compare(new Money(200, $this->egp)))->toBe(-1)
        ->and($a->compare(new Money(100, $this->egp)))->toBe(0)
        ->and($a->compare(new Money(50, $this->egp)))->toBe(1)
        ->and($a->equals(new Money(100, $this->egp)))->toBeTrue()
        ->and($a->equals(new Money(100, $this->usd)))->toBeFalse();
});

it('creates zero', function () {
    expect(Money::zero($this->kwd)->minor)->toBe(0)->and(Money::zero($this->kwd)->currency->code)->toBe('KWD');
});

it('converts between currencies of different decimals', function () {
    // 100.00 EGP * 0.0063 = 0.630 KWD
    $kwd = (new Money(10000, $this->egp))->convert(new ExchangeRate($this->egp, $this->kwd, '0.0063'));

    expect($kwd->minor)->toBe(630)->and($kwd->currency->code)->toBe('KWD');
});

it('converts into a zero-decimal currency', function () {
    expect((new Money(10000, $this->usd))->convert(new ExchangeRate($this->usd, $this->jpy, '150.5'))->minor)->toBe(15050);
});

it('rounds half away from zero when converting', function () {
    $rate = new ExchangeRate($this->usd, $this->jpy, '150');

    expect((new Money(1, $this->usd))->convert($rate)->minor)->toBe(2)
        ->and((new Money(-1, $this->usd))->convert($rate)->minor)->toBe(-2);
});

it('converts with an identity rate unchanged', function () {
    expect((new Money(12345, $this->egp))->convert(ExchangeRate::identity($this->egp))->minor)->toBe(12345);
});

it('refuses to convert with a rate of another base currency', function () {
    (new Money(100, $this->egp))->convert(new ExchangeRate($this->usd, $this->egp, '50'));
})->throws(CurrencyMismatch::class);

it('allocates so the parts always add up', function (int $minor, array $ratios, array $expected) {
    $parts = (new Money($minor, $this->egp))->allocate($ratios);

    expect(array_map(fn (Money $p) => $p->minor, $parts))->toBe($expected)
        ->and(array_sum(array_map(fn (Money $p) => $p->minor, $parts)))->toBe($minor);
})->with([
    [100, [1, 1, 1], [34, 33, 33]],
    [5, [70, 30], [4, 1]],
    [100, [50, 50], [50, 50]],
    [-100, [1, 1, 1], [-34, -33, -33]],
    [1, [1, 1], [1, 0]],
    [0, [1, 2], [0, 0]],
    [1000, [3, 7], [300, 700]],
]);

it('refuses to allocate across nothing', function (array $ratios) {
    (new Money(100, $this->egp))->allocate($ratios);
})->with([[[]], [[0, 0]]])->throws(InvalidMoneyAmount::class);
