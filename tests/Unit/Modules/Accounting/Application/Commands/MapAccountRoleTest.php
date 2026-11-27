<?php

use Modules\Accounting\Application\AccountRoleResolver;
use Modules\Accounting\Application\Commands\MapAccountRole;
use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Enums\AccountStatus;
use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Exceptions\AccountRoleError;
use Modules\Accounting\Domain\Models\AccountMapping;
use Modules\Shared\Domain\Models\AuditLog;

covers(MapAccountRole::class);

beforeEach(function () {
    ['company' => $this->company] = bootstrapTenant();
    $this->command = app(MapAccountRole::class);
});

it('remaps a role to another account of the right type', function () {
    $other = makeAccount($this->company, '9301', AccountType::Asset);

    $mapping = $this->command->handle(AccountRole::Cash, $other);

    expect($mapping->account_id)->toBe($other->id)
        ->and(AccountMapping::where('role', 'cash')->count())->toBe(1)
        ->and(app(AccountRoleResolver::class)->accountId(AccountRole::Cash))->toBe($other->id);
});

it('creates a mapping for a role that had none', function () {
    AccountMapping::where('role', 'bank_charges')->delete();
    $account = makeAccount($this->company, '9302', AccountType::Expense);

    $this->command->handle(AccountRole::BankCharges, $account);

    expect(AccountMapping::where('role', 'bank_charges')->first()->account_id)->toBe($account->id)
        ->and(AuditLog::where('entity_type', 'account_mapping')->latest('id')->first()->action)->toBe('mapped');
});

it('audits a remapping with the previous account', function () {
    $previous = accountByRole(AccountRole::Cash)->id;
    $this->command->handle(AccountRole::Cash, makeAccount($this->company, '9303'));

    $log = AuditLog::where('entity_type', 'account_mapping')->latest('id')->first();

    expect($log->action)->toBe('remapped')->and($log->old_values)->toBe(['account_id' => $previous]);
});

it('rejects an account of the wrong type', function () {
    $this->command->handle(AccountRole::Cash, makeAccount($this->company, '9304', AccountType::Revenue));
})->throws(AccountRoleError::class);

it('rejects header and inactive accounts', function () {
    $header = $this->company->accounts()->where('code', '12')->first();
    expect(fn () => $this->command->handle(AccountRole::Cash, $header))->toThrow(AccountRoleError::class);

    $inactive = makeAccount($this->company, '9305');
    $inactive->update(['status' => AccountStatus::Inactive]);
    expect(fn () => $this->command->handle(AccountRole::Cash, $inactive))->toThrow(AccountRoleError::class);
});
