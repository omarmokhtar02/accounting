<?php

use Modules\Accounting\Application\Import\JournalsImporter;
use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Enums\JournalStatus;
use Modules\Accounting\Domain\Models\JournalEntry;

covers(JournalsImporter::class);

beforeEach(function () {
    bootstrapTenant();
    $this->importer = app(JournalsImporter::class);
    $this->cash = accountByRole(AccountRole::Cash);
    $this->sales = accountByRole(AccountRole::SalesRevenue);
});

function journalRow(string $ref, string $date, string $code, string $debit = '', string $credit = '', string $description = ''): array
{
    return ['ref' => $ref, 'date' => $date, 'account_code' => $code, 'debit' => $debit, 'credit' => $credit, 'description' => $description];
}

function validRows($test): array
{
    return [
        2 => journalRow('A1', '2025-12-31', $test->cash->code, '1,500.25', '', 'Opening'),
        3 => journalRow('A1', '2025-12-31', $test->sales->code, '', '1500.25'),
        4 => journalRow('A2', '2026-01-05', $test->cash->code, '10'),
        5 => journalRow('A2', '2026-01-05', $test->sales->code, '', '10'),
    ];
}

it('previews without creating entries', function () {
    $report = $this->importer->run(validRows($this), 'hash', false);

    expect($report->hasErrors())->toBeFalse()->and(JournalEntry::count())->toBe(0);
});

it('creates one balanced draft per ref', function () {
    $report = $this->importer->run(validRows($this), 'hash', true);
    $first = JournalEntry::where('description', 'Import A1')->first();

    expect($report->created)->toBe(2)->and(JournalEntry::count())->toBe(2)
        ->and($first->status)->toBe(JournalStatus::Draft)
        ->and($first->entry_date->toDateString())->toBe('2025-12-31')
        ->and($first->lines->sum('debit'))->toBe(150025)->and($first->lines->sum('credit'))->toBe(150025);
});

it('is idempotent: the same file twice creates nothing new', function () {
    $this->importer->run(validRows($this), 'hash', true);
    $report = $this->importer->run(validRows($this), 'hash', true);

    expect($report->created)->toBe(0)->and($report->skipped)->toBe(2)->and(JournalEntry::count())->toBe(2);
});

it('reports each kind of problem and writes nothing', function (callable $rows, string $key) {
    $report = $this->importer->run($rows($this), 'hash', true);

    expect(collect($report->errors)->pluck('key')->all())->toContain($key)->and(JournalEntry::count())->toBe(0);
})->with([
    'no ref' => [fn ($t) => [2 => journalRow('', '2026-01-01', $t->cash->code, '1')], 'accounting.import.bad_ref'],
    'bad date' => [fn ($t) => [2 => journalRow('R', '1/1/2026', $t->cash->code, '1')], 'accounting.import.bad_date'],
    'unknown account' => [fn ($t) => [2 => journalRow('R', '2026-01-01', 'nope', '1')], 'accounting.import.unknown_account'],
    'bad amount' => [fn ($t) => [2 => journalRow('R', '2026-01-01', $t->cash->code, 'abc')], 'accounting.import.bad_amount'],
    'too many decimals' => [fn ($t) => [2 => journalRow('R', '2026-01-01', $t->cash->code, '1.234')], 'accounting.import.bad_amount'],
    'both sides' => [fn ($t) => [2 => journalRow('R', '2026-01-01', $t->cash->code, '1', '1')], 'accounting.import.one_side'],
    'no side' => [fn ($t) => [2 => journalRow('R', '2026-01-01', $t->cash->code)], 'accounting.import.one_side'],
    'negative' => [fn ($t) => [2 => journalRow('R', '2026-01-01', $t->cash->code, '-5')], 'accounting.import.one_side'],
    'mixed dates' => [fn ($t) => [
        2 => journalRow('R', '2026-01-01', $t->cash->code, '1'), 3 => journalRow('R', '2026-01-02', $t->sales->code, '', '1'),
    ], 'accounting.import.mixed_dates'],
    'unbalanced' => [fn ($t) => [
        2 => journalRow('R', '2026-01-01', $t->cash->code, '2'), 3 => journalRow('R', '2026-01-01', $t->sales->code, '', '1'),
    ], 'accounting.import.unbalanced'],
    'single line' => [fn ($t) => [2 => journalRow('R', '2026-01-01', $t->cash->code, '1')], 'accounting.import.unbalanced'],
    'control account' => [fn ($t) => [
        2 => journalRow('R', '2026-01-01', accountByRole(AccountRole::AccountsReceivable)->code, '1'), 3 => journalRow('R', '2026-01-01', $t->sales->code, '', '1'),
    ], 'accounting.import.control_account'],
]);
