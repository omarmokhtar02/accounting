<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration: seeds the global permission catalogue (module.action names, guard "web").
 * Permissions are global; roles are tenant scoped (see TenantRoleProvisioner). The list is a
 * snapshot, so later permissions must be added by a new migration, never by editing this one.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'companies.manage',
        'accounts.view', 'accounts.create', 'accounts.update', 'accounts.archive',
        'periods.view', 'periods.manage', 'periods.close',
        'journals.view', 'journals.create', 'journals.post', 'journals.post_own', 'journals.reverse',
        'audit.view',
        'users.manage', 'roles.manage',
    ];

    public function up(): void
    {
        $now = now();

        DB::table(config('permission.table_names.permissions'))->insertOrIgnore(
            array_map(fn (string $name) => [
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ], self::PERMISSIONS),
        );
    }

    public function down(): void
    {
        DB::table(config('permission.table_names.permissions'))
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', 'web')
            ->delete();
    }
};
