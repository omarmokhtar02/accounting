<?php

use Modules\Accounting\Application\Import\ImportDate;

covers(ImportDate::class);

it('parses a valid date', function () {
    expect(ImportDate::parse('2026-03-31')->toDateString())->toBe('2026-03-31');
});

it('rejects anything that is not a real Y-m-d date', function (string $value) {
    expect(ImportDate::parse($value))->toBeNull();
})->with(['', 'yesterday', '1/2/2026', '2026-02-31', '2026-3-1', '2026-03-31 10:00', '2026-13-01']);
