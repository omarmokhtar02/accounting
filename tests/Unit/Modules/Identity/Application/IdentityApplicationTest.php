<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Application\CreateTenantUser;
use Modules\Identity\Application\RegisterTenant;
use Modules\Identity\Application\SyncRolePermissions;
use Modules\Identity\Application\TenantRoleProvisioner;
use Modules\Localization\Domain\Exceptions\UnknownCountry;
use Modules\Shared\Domain\Models\AuditLog;
use Modules\Tenancy\Domain\Models\Tenant;
use Modules\Tenancy\Infrastructure\TenantContext;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;

covers(CreateTenantUser::class, RegisterTenant::class, SyncRolePermissions::class, TenantRoleProvisioner::class);

describe('RegisterTenant', function () {
    it('creates tenant, owner, company and chart in one go', function () {
        $user = app(RegisterTenant::class)->handle('Owner', 'o@example.com', Hash::make('p'), 'Acme', 'SA', 'ar');

        expect($user->tenant->name)->toBe('Acme')->and($user->tenant->locale)->toBe('ar')
            ->and($user->locale)->toBe('ar')
            ->and($user->companies)->toHaveCount(1)
            ->and($user->companies->first()->country_code)->toBe('SA')
            ->and($user->companies->first()->base_currency)->toBe('SAR');

        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
        expect($user->hasRole('owner'))->toBeTrue()->and($user->can('journals.post_own'))->toBeTrue();
    });

    it('keeps tenants isolated from each other', function () {
        $a = app(RegisterTenant::class)->handle('A', 'a@example.com', Hash::make('p'), 'A Co', 'EG', 'en');
        $b = app(RegisterTenant::class)->handle('B', 'b@example.com', Hash::make('p'), 'B Co', 'KW', 'en');

        expect($a->tenant_id)->not->toBe($b->tenant_id)
            ->and(Tenant::count())->toBe(2)
            ->and(Role::where('tenant_id', $a->tenant_id)->count())->toBe(6);
    });

    it('rolls everything back for an unknown country', function () {
        expect(fn () => app(RegisterTenant::class)->handle('O', 'o@example.com', Hash::make('p'), 'Acme', 'ZZ', 'en'))
            ->toThrow(UnknownCountry::class)
            ->and(User::count())->toBe(0)->and(Tenant::count())->toBe(0);
    });
});

describe('CreateTenantUser', function () {
    beforeEach(function () {
        ['user' => $this->owner, 'company' => $this->company] = bootstrapTenant();
    });

    it('adds a user to the tenant with a role and company access', function () {
        $user = app(CreateTenantUser::class)->handle('Sara', 's@example.com', Hash::make('p'), 'accountant', [$this->company->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

        expect($user->tenant_id)->toBe($this->owner->tenant_id)->and($user->hasRole('accountant'))->toBeTrue()
            ->and($user->can('journals.create'))->toBeTrue()->and($user->can('journals.post'))->toBeFalse()
            ->and($user->companies->pluck('id')->all())->toBe([$this->company->id])
            ->and($user->locale)->toBe(app()->getLocale());
    });

    it('ignores company ids outside the tenant', function () {
        ['company' => $foreign] = bootstrapTenant('f@example.com', 'Foreign');
        app(TenantContext::class)->setTenant($this->owner->tenant);

        $user = app(CreateTenantUser::class)->handle('X', 'x@example.com', Hash::make('p'), 'viewer', [$foreign->id, $this->company->id]);

        expect($user->companies->pluck('id')->all())->toBe([$this->company->id]);
    });
});

describe('TenantRoleProvisioner', function () {
    it('defines six roles and the permission catalogue', function () {
        expect(array_keys(TenantRoleProvisioner::roles()))->toBe(['owner', 'admin', 'accountant', 'senior_accountant', 'auditor', 'viewer'])
            ->and(TenantRoleProvisioner::PERMISSIONS)->toContain('journals.post', 'users.manage', 'roles.manage', 'periods.close');
    });

    it('gives the owner everything and admin everything except self posting', function () {
        $roles = TenantRoleProvisioner::roles();

        expect($roles['owner'])->toBe(TenantRoleProvisioner::PERMISSIONS)
            ->and($roles['admin'])->not->toContain('journals.post_own')->toContain('users.manage');
    });

    it('keeps auditors and viewers read-only', function () {
        $roles = TenantRoleProvisioner::roles();

        foreach (['auditor', 'viewer'] as $role) {
            foreach ($roles[$role] as $permission) {
                // Read-only roles can look; auditors may also take a copy out (export) but never change data.
                expect($permission)->toMatch('/\.view$|^reports\.export$/');
            }
        }
    });

    it('groups permissions by module', function () {
        $groups = TenantRoleProvisioner::grouped();

        expect(array_keys($groups))->toBe(['companies', 'accounts', 'periods', 'journals', 'audit', 'users', 'roles', 'tax', 'reports', 'imports'])
            ->and($groups['journals'])->toContain('journals.post', 'journals.reverse');
    });

    it('provisions idempotently per tenant', function () {
        $tenant = Tenant::factory()->create();
        $provisioner = app(TenantRoleProvisioner::class);

        $provisioner->provision($tenant->id);
        $provisioner->provision($tenant->id);

        expect(Role::where('tenant_id', $tenant->id)->count())->toBe(6)
            ->and(Permission::count())->toBe(count(TenantRoleProvisioner::PERMISSIONS));
    });

    it('restores the previous permission team after provisioning', function () {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(777);

        app(TenantRoleProvisioner::class)->provision(Tenant::factory()->create()->id);

        expect($registrar->getPermissionsTeamId())->toBe(777);
    });
});

describe('SyncRolePermissions', function () {
    beforeEach(function () {
        ['user' => $this->owner] = bootstrapTenant();
        $this->role = Role::where('tenant_id', $this->owner->tenant_id)->where('name', 'viewer')->first();
    });

    it('replaces the permissions and drops unknown names', function () {
        app(SyncRolePermissions::class)->handle($this->role, ['journals.view', 'journals.post', 'nope.nope']);

        expect($this->role->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe(['journals.post', 'journals.view']);
    });

    it('audits old and new permissions', function () {
        app(SyncRolePermissions::class)->handle($this->role, ['accounts.view']);

        $log = AuditLog::where('entity_type', 'role')->where('action', 'permissions_updated')->first();

        expect($log->old_values['permissions'])->toContain('journals.view')->and($log->new_values['permissions'])->toBe(['accounts.view']);
    });

    it('never edits the owner role', function () {
        $owner = Role::where('tenant_id', $this->owner->tenant_id)->where('name', 'owner')->first();

        expect(fn () => app(SyncRolePermissions::class)->handle($owner, []))->toThrow(HttpException::class)
            ->and($owner->fresh()->permissions)->toHaveCount(count(TenantRoleProvisioner::PERMISSIONS));
    });
});
