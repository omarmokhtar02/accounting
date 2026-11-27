<?php

namespace Modules\Identity\Application;

use Modules\Shared\Application\RecordAudit;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class SyncRolePermissions
{
    public function __construct(private readonly RecordAudit $audit) {}

    /**
     * Replaces a role's permissions with the given names. Unknown names are dropped, and the
     * owner role is immutable so a tenant can never lock itself out.
     *
     * @param  list<string>  $permissions
     */
    public function handle(Role $role, array $permissions): Role
    {
        abort_if($role->name === 'owner', 403);

        $allowed = array_values(array_intersect($permissions, TenantRoleProvisioner::PERMISSIONS));
        $old = $role->permissions->pluck('name')->sort()->values()->all();

        $role->syncPermissions($allowed);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->audit->handle('role', $role->id, 'permissions_updated', ['permissions' => $old], ['permissions' => collect($allowed)->sort()->values()->all()]);

        return $role;
    }
}
