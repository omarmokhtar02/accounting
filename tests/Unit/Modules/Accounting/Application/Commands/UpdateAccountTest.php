<?php

use Modules\Accounting\Application\Commands\UpdateAccount;
use Modules\Accounting\Application\DTOs\UpdateAccountData;
use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Enums\CashFlowCategory;
use Modules\Accounting\Domain\Exceptions\InvalidAccountChange;
use Modules\Accounting\Domain\Models\Account;
use Modules\Shared\Domain\Models\AuditLog;

covers(UpdateAccount::class);

beforeEach(function () {
    ['user' => $this->user, 'company' => $this->company] = bootstrapTenant();
    $this->command = app(UpdateAccount::class);
});

function accountData(Account $account, array $override = []): UpdateAccountData
{
    $a = array_merge([
        'code' => $account->code, 'name' => $account->name, 'nameAr' => $account->name_ar, 'type' => $account->type,
        'parentId' => $account->parent_id, 'isPostable' => $account->is_postable, 'cashFlow' => $account->cash_flow_category,
    ], $override);

    return new UpdateAccountData($a['code'], $a['name'], $a['nameAr'], $a['type'], $a['parentId'], $a['isPostable'], $a['cashFlow']);
}

it('renames an unused account and changes every field', function () {
    $account = makeAccount($this->company, '9401');

    $updated = $this->command->handle($account, accountData($account, [
        'code' => '9402', 'name' => 'Renamed', 'nameAr' => 'اسم جديد', 'type' => AccountType::Liability, 'cashFlow' => CashFlowCategory::Financing,
    ]));

    expect($updated->code)->toBe('9402')->and($updated->name)->toBe('Renamed')->and($updated->name_ar)->toBe('اسم جديد')
        ->and($updated->type)->toBe(AccountType::Liability)->and($updated->cash_flow_category)->toBe(CashFlowCategory::Financing);
});

it('locks code and type once the account has ledger activity', function () {
    openFiscalYear2026();
    postedJournal($this->user);
    $cash = accountByRole(AccountRole::Cash);

    expect(fn () => $this->command->handle($cash, accountData($cash, ['code' => '1299'])))->toThrow(InvalidAccountChange::class)
        ->and(fn () => $this->command->handle($cash, accountData($cash, ['type' => AccountType::Liability])))->toThrow(InvalidAccountChange::class);

    // Names and cash-flow category stay editable.
    expect($this->command->handle($cash, accountData($cash, ['name' => 'Main till']))->name)->toBe('Main till');
});

it('locks an account that plays a role even without ledger lines', function () {
    $vat = accountByRole(AccountRole::VatInput);

    $this->command->handle($vat, accountData($vat, ['code' => '1299']));
})->throws(InvalidAccountChange::class);

it('cannot turn a used account into a header', function () {
    openFiscalYear2026();
    postedJournal($this->user);
    $cash = accountByRole(AccountRole::Cash);

    $this->command->handle($cash, accountData($cash, ['isPostable' => false]));
})->throws(InvalidAccountChange::class);

it('cannot make an account with sub-accounts postable', function () {
    $header = $this->company->accounts()->where('code', '12')->first();

    $this->command->handle($header, accountData($header, ['isPostable' => true]));
})->throws(InvalidAccountChange::class);

it('re-parents under a header of the same type', function () {
    $account = makeAccount($this->company, '9403');
    $header = $this->company->accounts()->where('code', '11')->first();

    expect($this->command->handle($account, accountData($account, ['parentId' => $header->id]))->parent_id)->toBe($header->id);
});

it('rejects parents of another type, parents with ledger lines and itself', function () {
    openFiscalYear2026();
    postedJournal($this->user);
    $account = makeAccount($this->company, '9404');
    $liabilityHeader = $this->company->accounts()->where('code', '21')->first();
    $cash = accountByRole(AccountRole::Cash);

    expect(fn () => $this->command->handle($account, accountData($account, ['parentId' => $liabilityHeader->id])))->toThrow(InvalidAccountChange::class)
        ->and(fn () => $this->command->handle($account, accountData($account, ['parentId' => $cash->id])))->toThrow(InvalidAccountChange::class)
        ->and(fn () => $this->command->handle($account, accountData($account, ['parentId' => $account->id])))->toThrow(InvalidAccountChange::class);
});

it('rejects a parent that is a descendant (cycle)', function () {
    $parent = Account::create(['company_id' => $this->company->id, 'code' => '9500', 'name' => 'P', 'type' => AccountType::Asset, 'is_postable' => false])->refresh();
    $child = Account::create(['company_id' => $this->company->id, 'code' => '9501', 'name' => 'C', 'type' => AccountType::Asset, 'is_postable' => false, 'parent_id' => $parent->id])->refresh();

    $this->command->handle($parent, accountData($parent, ['parentId' => $child->id]));
})->throws(InvalidAccountChange::class);

it('audits old and new values', function () {
    $account = makeAccount($this->company, '9405');
    $this->command->handle($account, accountData($account, ['name' => 'After']));

    $log = AuditLog::where('entity_type', 'account')->where('entity_id', $account->id)->where('action', 'updated')->first();

    expect($log->old_values['name'])->toBe('Account 9405')->and($log->new_values['name'])->toBe('After');
});
