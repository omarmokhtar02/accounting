<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration: the admin permission catalogue and the four built-in admin roles. Admins can create
 * further roles from the dashboard; super_admin always has every permission. A snapshot: new
 * permissions are added by a new migration, never by editing this one.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'dashboard' => ['dashboard.view'],
        'tenants' => ['tenants.view', 'tenants.manage'],
        'subscriptions' => ['subscriptions.manage'],
        'plans' => ['plans.manage'],
        'settings' => ['settings.manage'],
        'tax' => ['tax.manage'],
        'admins' => ['admins.manage'],
        'roles' => ['roles.manage'],
        'audit' => ['audit.view'],
    ];

    private const ROLES = [
        'super_admin' => ['Super admin', 'مدير النظام', '*'],
        'support' => ['Support', 'الدعم الفني', ['dashboard.view', 'tenants.view', 'tenants.manage', 'subscriptions.manage']],
        'finance' => ['Finance', 'المالية', ['dashboard.view', 'tenants.view', 'subscriptions.manage', 'plans.manage', 'audit.view']],
        'viewer' => ['Viewer', 'مشاهد', ['dashboard.view', 'tenants.view', 'audit.view']],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::PERMISSIONS as $module => $keys) {
            foreach ($keys as $key) {
                DB::table('admin_permissions')->insertOrIgnore(['key' => $key, 'module' => $module, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $all = DB::table('admin_permissions')->pluck('id', 'key');

        foreach (self::ROLES as $key => [$en, $ar, $permissions]) {
            DB::table('admin_roles')->insertOrIgnore(['key' => $key, 'name_en' => $en, 'name_ar' => $ar, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now]);
            $roleId = DB::table('admin_roles')->where('key', $key)->value('id');

            $ids = $permissions === '*' ? $all->values() : collect($permissions)->map(fn ($p) => $all[$p]);

            foreach ($ids as $permissionId) {
                DB::table('admin_role_permission')->insertOrIgnore(['admin_role_id' => $roleId, 'admin_permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        DB::table('admin_roles')->whereIn('key', array_keys(self::ROLES))->delete();
        DB::table('admin_permissions')->whereIn('key', collect(self::PERMISSIONS)->flatten()->all())->delete();
    }
};
