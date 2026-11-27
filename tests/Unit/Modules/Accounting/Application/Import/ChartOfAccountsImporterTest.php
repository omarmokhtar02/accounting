<?php

use Modules\Accounting\Application\Import\ChartOfAccountsImporter;
use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Models\Account;

covers(ChartOfAccountsImporter::class);

beforeEach(function () {
    ['company' => $this->company] = bootstrapTenant();
    $this->importer = app(ChartOfAccountsImporter::class);
});

function accountRow(string $code, string $name = 'Name', string $type = 'asset', string $parent = '', string $postable = '', string $category = ''): array
{
    return ['code' => $code, 'name' => $name, 'type' => $type, 'name_ar' => 'اسم', 'parent_code' => $parent, 'postable' => $postable, 'cash_flow' => $category];
}

it('previews without writing anything', function () {
    $before = Account::count();
    $report = $this->importer->run([2 => accountRow('9500')], 'h', false);

    expect($report->hasErrors())->toBeFalse()->and($report->committed)->toBeFalse()->and(Account::count())->toBe($before);
});

it('creates accounts, groups before their children, and turns parents into groups', function () {
    $report = $this->importer->run([
        2 => accountRow('9511', 'Child', 'expense', '9510'),
        3 => accountRow('9510', 'Group', 'expense'),
    ], 'h', true);

    $group = Account::where('code', '9510')->first();
    $child = Account::where('code', '9511')->first();

    expect($report->created)->toBe(2)->and($report->committed)->toBeTrue()
        ->and($group->is_postable)->toBeFalse()
        ->and($child->parent_id)->toBe($group->id)->and($child->is_postable)->toBeTrue()
        ->and($child->type)->toBe(AccountType::Expense)->and($child->name_ar)->toBe('اسم');
});

it('honours the postable and cash_flow columns', function () {
    $this->importer->run([2 => accountRow('9520', 'Bank', 'asset', '', 'no', 'cash')], 'h', true);

    $account = Account::where('code', '9520')->first();

    expect($account->is_postable)->toBeFalse()->and($account->cash_flow_category->value)->toBe('cash');
});

it('skips codes that already exist and never changes them', function () {
    $existing = makeAccount($this->company, '9530');
    $report = $this->importer->run([2 => accountRow('9530', 'Renamed')], 'h', true);

    expect($report->created)->toBe(0)->and($report->skipped)->toBe(1)->and($existing->fresh()->name)->toBe('Account 9530');
});

it('can attach new accounts to an existing group account', function () {
    $this->importer->run([2 => accountRow('9540', 'Group', 'asset', '', 'no')], 'h', true);
    $report = $this->importer->run([2 => accountRow('9541', 'Leaf', 'asset', '9540')], 'h', true);

    expect($report->created)->toBe(1)->and(Account::where('code', '9541')->first()->parent_id)->toBe(Account::where('code', '9540')->first()->id);
});

it('reports every problem with its row and writes nothing', function (array $rows, string $key) {
    $before = Account::count();
    $report = $this->importer->run($rows, 'h', true);

    expect(collect($report->errors)->pluck('key')->all())->toContain($key)->and($report->committed)->toBeFalse()->and(Account::count())->toBe($before);
})->with([
    'no code' => [[2 => accountRow('')], 'accounting.import.bad_code'],
    'long code' => [[2 => accountRow(str_repeat('1', 31))], 'accounting.import.bad_code'],
    'no name' => [[2 => accountRow('9550', '')], 'accounting.import.bad_name'],
    'bad type' => [[2 => accountRow('9550', 'N', 'cash')], 'accounting.import.bad_type'],
    'bad cash flow' => [[2 => accountRow('9550', 'N', 'asset', '', '', 'weird')], 'accounting.import.bad_cash_flow'],
    'duplicate' => [[2 => accountRow('9550'), 3 => accountRow('9550')], 'accounting.import.duplicate_code'],
    'unknown parent' => [[2 => accountRow('9550', 'N', 'asset', '0000')], 'accounting.import.unknown_parent'],
    'own parent' => [[2 => accountRow('9550', 'N', 'asset', '9550')], 'accounting.import.unknown_parent'],
    'cycle' => [[2 => accountRow('9550', 'A', 'asset', '9551'), 3 => accountRow('9551', 'B', 'asset', '9550')], 'accounting.import.parent_cycle'],
]);

it('refuses a parent that is a leaf account already in the books', function () {
    makeAccount($this->company, '9560');

    $report = $this->importer->run([2 => accountRow('9561', 'Leaf', 'asset', '9560')], 'h', false);

    expect($report->errors[0]['key'])->toBe('accounting.import.parent_postable');
});
