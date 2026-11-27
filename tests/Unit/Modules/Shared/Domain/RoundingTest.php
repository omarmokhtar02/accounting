<?php

use Modules\Shared\Domain\Rounding;

covers(Rounding::class);

it('rounds half away from zero', function (string $input, int $expected) {
    expect(Rounding::toInteger($input))->toBe($expected);
})->with([
    ['2.5', 3], ['2.4999', 2], ['2.5000001', 3], ['0.5', 1], ['0.4', 0], ['0', 0],
    ['-2.5', -3], ['-2.4999', -2], ['-0.5', -1], ['-0.4', 0], ['1234567.499999', 1234567],
]);

it('keeps whole numbers unchanged', function () {
    expect(Rounding::toInteger('42'))->toBe(42)->and(Rounding::toInteger('-42'))->toBe(-42);
});
