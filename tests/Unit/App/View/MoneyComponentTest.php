<?php

use App\View\Components\Money;
use Illuminate\Support\Facades\Blade;
use Modules\Shared\Domain\Exceptions\UnknownCurrency;

covers(Money::class);

it('renders minor units with the currency decimals', function (int $minor, string $currency, string $expected) {
    expect(Blade::render('<x-money :minor="$minor" :currency="$currency" />', compact('minor', 'currency')))->toBe($expected);
})->with([
    [123456, 'EGP', '1,234.56'],
    [1234567, 'KWD', '1,234.567'],
    [1234567, 'JPY', '1,234,567'],
    [0, 'SAR', '0.00'],
    [-5000, 'EGP', '-50.00'],
]);

it('can render zero as empty (for debit/credit columns)', function () {
    expect(Blade::render('<x-money :minor="0" currency="EGP" blank-zero />'))->toBe('')
        ->and(Blade::render('<x-money :minor="100" currency="EGP" blank-zero />'))->toBe('1.00');
});

it('rejects an unknown currency instead of guessing decimals', function () {
    (new Money(100, 'ZZZ'))->render();
})->throws(UnknownCurrency::class);

it('renders through the component class directly', function () {
    expect((new Money(250, 'KWD'))->render())->toBe('0.250');
});
