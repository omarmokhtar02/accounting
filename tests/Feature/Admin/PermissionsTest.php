<?php

use App\Models\User;
use Modules\Tenancy\Infrastructure\TenantContext;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    ['user' => $this->owner, 'company' => $this->company] = bootstrapTenant();
});

it('lets the owner create a user with a role and company access', function () {
    $this->post(route('admin.users.store'), [
        'name' => 'Sara', 'email' => 'sara@example.com', 'password' => 'secret-pass-1',
        'role' => 'accountant', 'company_ids' => [$this->company->id],
    ])->assertRedirect(route('admin.users.index'));

    $sara = User::where('email', 'sara@example.com')->first();
    app(PermissionRegistrar::class)->setPermissionsTeamId($sara->tenant_id);

    app(TenantContext::class)->setTenant($this->owner->tenant);

    expect($sara->tenant_id)->toBe($this->owner->tenant_id)
        ->and($sara->hasRole('accountant'))->toBeTrue()
        ->and($sara->can('journals.create'))->toBeTrue()
        ->and($sara->can('journals.post'))->toBeFalse()
        ->and($sara->companies()->count())->toBe(1);
});

it('refuses to assign the owner role or a foreign company from the form', function () {
    ['company' => $foreign] = bootstrapTenant('other@example.com', 'Other');
    $this->actingAs($this->owner);
    app(TenantContext::class)->setTenant($this->owner->tenant);

    $this->post(route('admin.users.store'), [
        'name' => 'X', 'email' => 'x@example.com', 'password' => 'secret-pass-1',
        'role' => 'owner', 'company_ids' => [$foreign->id],
    ])->assertSessionHasErrors(['role', 'company_ids.0']);
});

it('denies user management to roles without the permission', function () {
    $this->post(route('admin.users.store'), [
        'name' => 'Vic', 'email' => 'vic@example.com', 'password' => 'secret-pass-1',
        'role' => 'viewer', 'company_ids' => [$this->company->id],
    ]);

    $viewer = User::where('email', 'vic@example.com')->first();
    app(PermissionRegistrar::class)->setPermissionsTeamId($viewer->tenant_id);

    $this->actingAs($viewer)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('admin.roles.index'))->assertForbidden();
});

it('updates a role\'s permissions and keeps the owner role locked', function () {
    $role = Role::where('name', 'viewer')->where('tenant_id', $this->owner->tenant_id)->first();

    $this->put(route('admin.roles.update', $role), ['permissions' => ['journals.view', 'journals.post', 'bogus.permission']])
        ->assertRedirect(route('admin.roles.index'));

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe(['journals.post', 'journals.view']);

    $owner = Role::where('name', 'owner')->where('tenant_id', $this->owner->tenant_id)->first();
    $this->put(route('admin.roles.update', $owner), ['permissions' => []])->assertForbidden();
});

it('cannot edit another tenant\'s role', function () {
    ['user' => $other] = bootstrapTenant('other@example.com', 'Other');
    $foreignRole = Role::where('name', 'viewer')->where('tenant_id', $other->tenant_id)->first();

    $this->actingAs($this->owner);
    app(TenantContext::class)->setTenant($this->owner->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->owner->tenant_id);

    $this->get(route('admin.roles.edit', $foreignRole))->assertNotFound();
});
