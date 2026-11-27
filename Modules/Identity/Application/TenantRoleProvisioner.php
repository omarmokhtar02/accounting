<?php

namespace Modules\Identity\Application;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the permission catalogue and creates the standard roles for one tenant.
 * Permissions are global; roles are tenant-scoped through spatie "teams".
 */
final class TenantRoleProvisioner
{
    public const PERMISSIONS = [
        'companies.manage',
        'accounts.view', 'accounts.create', 'accounts.update', 'accounts.archive',
        'periods.view', 'periods.manage', 'periods.close',
        'journals.view', 'journals.create', 'journals.post', 'journals.post_own', 'journals.reverse',
        'audit.view',
        'users.manage', 'roles.manage',
        'tax.view',
        'reports.view', 'reports.export',
        'imports.run',
    ];

    /**
     * Permissions grouped by module (the part before the dot) for the role editor.
     *
     * @return array<string, list<string>>
     */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::PERMISSIONS as $permission) {
            $groups[explode('.', $permission)[0]][] = $permission;
        }

        return $groups;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function roles(): array
    {
        $all = self::PERMISSIONS;

        return [
            'owner' => $all,
            'admin' => array_values(array_diff($all, ['journals.post_own'])),
            'accountant' => [
                'accounts.view', 'accounts.create', 'accounts.update', 'periods.view',
                'journals.view', 'journals.create', 'tax.view', 'reports.view',
            ],
            'senior_accountant' => [
                'accounts.view', 'accounts.create', 'accounts.update', 'periods.view',
                'journals.view', 'journals.create', 'journals.post', 'journals.reverse', 'tax.view', 'reports.view', 'reports.export', 'imports.run',
            ],
            'auditor' => ['accounts.view', 'periods.view', 'journals.view', 'audit.view', 'tax.view', 'reports.view', 'reports.export'],
            'viewer' => ['accounts.view', 'periods.view', 'journals.view', 'tax.view', 'reports.view'],
        ];
    }

    public function provision(int $tenantId): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeam = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenantId);

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (self::roles() as $name => $permissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($permissions);
        }

        $registrar->setPermissionsTeamId($previousTeam);
    }
}
