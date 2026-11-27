<?php

use App\Models\User;
use Modules\Identity\Presentation\Controllers\LocaleController;
use Modules\Identity\Presentation\Controllers\RoleController;
use Modules\Identity\Presentation\Controllers\UserController;
use Modules\Identity\Presentation\Middleware\SetLocale;
use Modules\Identity\Providers\IdentityServiceProvider;
use Modules\Tenancy\Infrastructure\TenantContext;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

covers(LocaleController::class, RoleController::class, UserController::class, SetLocale::class, IdentityServiceProvider::class);

describe('language switching', function () {
    it('stores the chosen language in the session and on the user', function () {
        ['user' => $user] = bootstrapTenant();

        $this->get(route('locale.update', 'ar'))->assertRedirect();

        expect(session('locale'))->toBe('ar')->and($user->fresh()->locale)->toBe('ar');
    });

    it('works for guests (login page) without a user to update', function () {
        $this->get(route('locale.update', 'ar'))->assertRedirect();

        expect(session('locale'))->toBe('ar');
    });

    it('rejects an unsupported language', function () {
        $this->get('/locale/fr')->assertNotFound();
    });

    it('applies the session language, then the user language, then the default', function () {
        ['user' => $user] = bootstrapTenant();
        $user->update(['locale' => 'ar']);

        $this->get(route('dashboard'))->assertSee('dir="rtl"', false);

        $this->withSession(['locale' => 'en'])->get(route('dashboard'))->assertSee('dir="ltr"', false);
    });

    it('falls back to the default for an unsupported stored language', function () {
        bootstrapTenant();

        $this->withSession(['locale' => 'xx'])->get(route('dashboard'))->assertSee('dir="ltr"', false);
        expect(app()->getLocale())->toBe(config('app.fallback_locale'));
    });

    it('lists exactly the supported languages', function () {
        expect(SetLocale::SUPPORTED)->toBe(['en', 'ar']);
    });
});

describe('users admin', function () {
    beforeEach(function () {
        ['user' => $this->owner, 'company' => $this->company] = bootstrapTenant();
    });

    it('lists the tenant users with their roles and companies', function () {
        $this->get(route('admin.users.index'))->assertOk()->assertSee($this->owner->email)->assertSee('Owner');
    });

    it('creates a user with a role and company access', function () {
        $this->get(route('admin.users.create'))->assertOk();
        $this->post(route('admin.users.store'), [
            'name' => 'Sara', 'email' => 'sara@example.com', 'password' => 'secret-pass-1',
            'role' => 'accountant', 'company_ids' => [$this->company->id],
        ])->assertRedirect(route('admin.users.index'));

        $sara = User::where('email', 'sara@example.com')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($sara->tenant_id);

        expect($sara->tenant_id)->toBe($this->owner->tenant_id)->and($sara->hasRole('accountant'))->toBeTrue();
    });

    it('never assigns the owner role or a foreign company', function () {
        ['company' => $foreign] = bootstrapTenant('o@example.com', 'Other');
        actingAsOwnerAgain($this->owner);

        $this->post(route('admin.users.store'), [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'secret-pass-1', 'role' => 'owner', 'company_ids' => [$foreign->id],
        ])->assertSessionHasErrors(['role', 'company_ids.0']);
    });

    it('validates the new user', function (array $input, string $field) {
        $this->post(route('admin.users.store'), $input + [
            'name' => 'N', 'email' => 'n@example.com', 'password' => 'secret-pass-1', 'role' => 'viewer', 'company_ids' => [$this->company->id],
        ])->assertSessionHasErrors($field);
    })->with([
        'no name' => [['name' => ''], 'name'],
        'bad email' => [['email' => 'nope'], 'email'],
        'duplicate email' => [['email' => 'owner@example.com'], 'email'],
        'weak password' => [['password' => 'short'], 'password'],
        'no companies' => [['company_ids' => []], 'company_ids'],
    ]);

    it('is forbidden without users.manage', function () {
        actingAsRole('accountant');

        $this->get(route('admin.users.index'))->assertForbidden();
        $this->post(route('admin.users.store'), [])->assertForbidden();
    });
});

describe('roles admin', function () {
    beforeEach(function () {
        ['user' => $this->owner] = bootstrapTenant();
        $this->viewer = Role::where('tenant_id', $this->owner->tenant_id)->where('name', 'viewer')->first();
    });

    it('lists the six roles with their permission counts', function () {
        $this->get(route('admin.roles.index'))->assertOk()->assertSee('Viewer')->assertSee('Auditor');
    });

    it('edits and updates a role\'s permissions', function () {
        $this->get(route('admin.roles.edit', $this->viewer))->assertOk()->assertSee('View journals');
        $this->put(route('admin.roles.update', $this->viewer), ['permissions' => ['journals.view', 'journals.post']])
            ->assertRedirect(route('admin.roles.index'));

        expect($this->viewer->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe(['journals.post', 'journals.view']);
    });

    it('keeps the owner role locked', function () {
        $owner = Role::where('tenant_id', $this->owner->tenant_id)->where('name', 'owner')->first();

        $this->put(route('admin.roles.update', $owner), ['permissions' => []])->assertForbidden();
    });

    it('hides other tenants\' roles', function () {
        ['user' => $other] = bootstrapTenant('o2@example.com', 'Other2');
        $foreign = Role::where('tenant_id', $other->tenant_id)->where('name', 'viewer')->first();
        actingAsOwnerAgain($this->owner);

        $this->get(route('admin.roles.edit', $foreign))->assertNotFound();
        $this->put(route('admin.roles.update', $foreign), ['permissions' => []])->assertNotFound();
    });

    it('is forbidden without roles.manage', function () {
        actingAsRole('senior_accountant');

        $this->get(route('admin.roles.index'))->assertForbidden();
    });
});

it('serves the identity views and routes from the module', function () {
    expect(view()->exists('identity::users.index'))->toBeTrue()
        ->and(route('locale.update', 'en'))->toContain('/locale/en')
        ->and(route('admin.roles.index'))->toContain('/admin/roles');
});

function actingAsOwnerAgain(User $owner): void
{
    test()->actingAs($owner);
    $context = app(TenantContext::class);
    $context->setTenant($owner->tenant);
    $context->setCompany($owner->companies()->first());
    app(PermissionRegistrar::class)->setPermissionsTeamId($owner->tenant_id);
}
