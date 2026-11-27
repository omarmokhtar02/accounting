<?php

use Modules\Accounting\Domain\Exceptions\JournalNotBalanced;
use Modules\Accounting\Domain\Services\JournalBalanceChecker;

covers(JournalBalanceChecker::class);

it('accepts balanced journals', function (array $lines) {
    (new JournalBalanceChecker)->assertBalanced($lines);

    expect(true)->toBeTrue();
})->with([
    'two lines' => [[['debit' => 500, 'credit' => 0], ['debit' => 0, 'credit' => 500]]],
    'split debit' => [[['debit' => 300, 'credit' => 0], ['debit' => 200, 'credit' => 0], ['debit' => 0, 'credit' => 500]]],
    'many lines' => [[['debit' => 1, 'credit' => 0], ['debit' => 0, 'credit' => 1], ['debit' => 99, 'credit' => 0], ['debit' => 0, 'credit' => 99]]],
]);

it('rejects unbalanced journals', function (array $lines) {
    (new JournalBalanceChecker)->assertBalanced($lines);
})->with([
    'credit short' => [[['debit' => 500, 'credit' => 0], ['debit' => 0, 'credit' => 400]]],
    'debit short' => [[['debit' => 400, 'credit' => 0], ['debit' => 0, 'credit' => 500]]],
    'off by one minor unit' => [[['debit' => 1000, 'credit' => 0], ['debit' => 0, 'credit' => 999]]],
])->throws(JournalNotBalanced::class);

it('rejects fewer than two lines', function (array $lines) {
    (new JournalBalanceChecker)->assertBalanced($lines);
})->with([[[]], [[['debit' => 500, 'credit' => 0]]]])->throws(JournalNotBalanced::class);

it('rejects a journal whose totals are both zero', function () {
    (new JournalBalanceChecker)->assertBalanced([['debit' => 0, 'credit' => 0], ['debit' => 0, 'credit' => 0]]);
})->throws(JournalNotBalanced::class);
