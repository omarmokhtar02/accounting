<?php

use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Enums\AccountStatus;
use Modules\Accounting\Domain\Models\Account;
use Modules\Accounting\Presentation\Controllers\AccountController;
use Modules\Accounting\Presentation\Requests\StoreAccountRequest;
use Modules\Accounting\Presentation\Requests\UpdateAccountRequest;
use Modules\Shared\Domain\Models\AuditLog;
use Modules\Tenancy\Domain\Models\Company;

covers(AccountController::class, StoreAccountRequest::class, UpdateAccountRequest::class);

beforeEach(function () {
    ['user' => $this->owner, 'company' => $this->company] = bootstrapTenant();
});

it('lists the company accounts with their status and actions', function () {
    $this->get(route('accounting.accounts.index'))
        ->assertOk()->assertSee('1213')->assertSee('Cash on hand')->assertSee(__('accounting.account_roles'));
});

it('shows the create form with types, cash-flow categories and parents', function () {
    $this->get(route('accounting.accounts.create'))->assertOk()->assertSee('Operating')->assertSee('Asset');
});

it('stores an account, defaults postable, and audits it', function () {
    $this->post(route('accounting.accounts.store'), [
        'code' => '9801', 'name' => 'Petty', 'name_ar' => 'نثرية', 'type' => 'asset', 'cash_flow_category' => 'cash',
    ])->assertRedirect(route('accounting.accounts.index'));

    restoreContext($this->owner);
    $account = Account::where('code', '9801')->first();

    expect($account->name_ar)->toBe('نثرية')->and($account->cash_flow_category->value)->toBe('cash')->and($account->is_postable)->toBeTrue()
        ->and(AuditLog::where('entity_type', 'account')->where('entity_id', $account->id)->where('action', 'created')->exists())->toBeTrue();
});

it('validates new accounts', function (array $input, string $field) {
    $this->post(route('accounting.accounts.store'), $input + ['code' => '9802', 'name' => 'X', 'type' => 'asset'])->assertSessionHasErrors($field);
})->with([
    'duplicate code' => [['code' => '1213'], 'code'],
    'missing name' => [['name' => ''], 'name'],
    'bad type' => [['type' => 'banana'], 'type'],
    'bad cash flow' => [['cash_flow_category' => 'x'], 'cash_flow_category'],
    'unknown parent' => [['parent_id' => 999999], 'parent_id'],
]);

it('rejects a parent from another company', function () {
    $other = Company::create(['name' => 'B', 'country_code' => 'EG', 'base_currency' => 'EGP']);
    $foreign = makeAccount($other, '9900');

    $this->post(route('accounting.accounts.store'), ['code' => '9803', 'name' => 'X', 'type' => 'asset', 'parent_id' => $foreign->id])
        ->assertSessionHasErrors('parent_id');
});

it('edits and updates an account', function () {
    $account = makeAccount($this->company, '9804');

    $this->get(route('accounting.accounts.edit', $account))->assertOk()->assertSee('9804');
    $this->put(route('accounting.accounts.update', $account), [
        'code' => '9804', 'name' => 'Renamed', 'type' => 'asset', 'cash_flow_category' => 'investing', 'is_postable' => '1',
    ])->assertRedirect(route('accounting.accounts.index'));

    restoreContext($this->owner);
    expect($account->fresh()->name)->toBe('Renamed')->and($account->fresh()->cash_flow_category->value)->toBe('investing');
});

it('shows a translated error when a locked field is changed', function () {
    $cash = accountByRole(AccountRole::Cash);

    $this->from(route('accounting.accounts.edit', $cash))->put(route('accounting.accounts.update', $cash), [
        'code' => '1299', 'name' => $cash->name, 'type' => 'asset', 'cash_flow_category' => 'cash',
    ])->assertSessionHasErrors('account');
});

it('changes status through the status endpoint', function () {
    $account = makeAccount($this->company, '9805');

    $this->post(route('accounting.accounts.status', $account), ['status' => 'inactive'])->assertSessionHas('status');
    restoreContext($this->owner);
    expect($account->fresh()->status)->toBe(AccountStatus::Inactive);

    $this->post(route('accounting.accounts.status', $account), ['status' => 'bogus'])->assertSessionHasErrors('status');
});

it('reports a forbidden status change as an error, not a crash', function () {
    $cash = accountByRole(AccountRole::Cash);

    $this->post(route('accounting.accounts.status', $cash), ['status' => 'inactive'])->assertSessionHasErrors('account');
});

it('returns 404 for an account of another company or tenant', function () {
    $other = Company::create(['name' => 'B', 'country_code' => 'EG', 'base_currency' => 'EGP']);
    $foreign = makeAccount($other, '9901');

    $this->get(route('accounting.accounts.edit', $foreign))->assertNotFound();
    $this->put(route('accounting.accounts.update', $foreign), ['code' => 'x', 'name' => 'x', 'type' => 'asset', 'cash_flow_category' => 'none'])->assertNotFound();
    $this->post(route('accounting.accounts.status', $foreign), ['status' => 'inactive'])->assertNotFound();
});

it('enforces permissions per action', function (string $role, string $method, string $route, int $status) {
    $account = makeAccount($this->company, '9806');
    actingAsRole($role);

    $params = $route === 'accounting.accounts.index' || $route === 'accounting.accounts.create' || $route === 'accounting.accounts.store' ? [] : [$account];
    $response = $this->{$method}(route($route, $params), $method === 'get' ? [] : ['code' => 'q', 'name' => 'q', 'type' => 'asset', 'cash_flow_category' => 'none', 'status' => 'inactive']);

    expect($response->getStatusCode())->toBe($status);
})->with([
    'viewer lists' => ['viewer', 'get', 'accounting.accounts.index', 200],
    'viewer cannot create form' => ['viewer', 'get', 'accounting.accounts.create', 403],
    'viewer cannot store' => ['viewer', 'post', 'accounting.accounts.store', 403],
    'viewer cannot edit' => ['viewer', 'get', 'accounting.accounts.edit', 403],
    'viewer cannot change status' => ['viewer', 'post', 'accounting.accounts.status', 403],
    'accountant can edit' => ['accountant', 'get', 'accounting.accounts.edit', 200],
    'accountant cannot archive' => ['accountant', 'post', 'accounting.accounts.status', 403],
]);
