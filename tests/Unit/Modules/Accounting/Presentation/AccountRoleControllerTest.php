<?php

use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Models\AccountMapping;
use Modules\Accounting\Presentation\Controllers\AccountRoleController;

covers(AccountRoleController::class);

beforeEach(function () {
    ['user' => $this->owner, 'company' => $this->company] = bootstrapTenant();
});

it('lists every role with its mapped account', function () {
    $response = $this->get(route('accounting.account-roles.index'))->assertOk();

    foreach (AccountRole::cases() as $role) {
        $response->assertSee($role->label());
    }
});

it('remaps a role', function () {
    $other = makeAccount($this->company, '9810');

    $this->put(route('accounting.account-roles.update'), ['role' => 'cash', 'account_id' => $other->id])->assertSessionHas('status');

    restoreContext($this->owner);
    expect(AccountMapping::where('role', 'cash')->first()->account_id)->toBe($other->id);
});

it('shows an error for an account of the wrong type', function () {
    $revenue = makeAccount($this->company, '9811', AccountType::Revenue);

    $this->put(route('accounting.account-roles.update'), ['role' => 'cash', 'account_id' => $revenue->id])->assertSessionHasErrors('role');
});

it('validates the role and the account', function () {
    $this->put(route('accounting.account-roles.update'), ['role' => 'nope', 'account_id' => 999999])->assertSessionHasErrors(['role', 'account_id']);
});

it('only lets people who may update accounts remap roles', function () {
    actingAsRole('viewer');

    $this->get(route('accounting.account-roles.index'))->assertOk();
    $this->put(route('accounting.account-roles.update'), ['role' => 'cash', 'account_id' => 1])->assertForbidden();
});
