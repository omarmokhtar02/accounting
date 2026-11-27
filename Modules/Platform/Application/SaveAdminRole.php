<?php

namespace Modules\Platform\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Platform\Domain\Exceptions\AdminOperationDenied;
use Modules\Platform\Domain\Models\AdminPermission;
use Modules\Platform\Domain\Models\AdminRole;

final class SaveAdminRole
{
    public function __construct(private readonly RecordPlatformAudit $audit) {}

    /**
     * Creates a custom role with the given permission keys (unknown keys are dropped).
     *
     * @param  list<string>  $permissionKeys
     */
    public function create(string $nameEn, string $nameAr, array $permissionKeys): AdminRole
    {
        return DB::transaction(function () use ($nameEn, $nameAr, $permissionKeys): AdminRole {
            $role = AdminRole::create([
                'key' => $this->uniqueKey($nameEn), 'name_en' => $nameEn, 'name_ar' => $nameAr, 'is_system' => false,
            ]);

            $role->permissions()->sync($this->ids($permissionKeys));
            $this->audit->handle('admin_role_created', 'admin_role', $role->id, ['permissions' => $this->clean($permissionKeys)]);

            return $role;
        });
    }

    /**
     * Updates a role's names and permissions. super_admin always keeps every permission, so it is not editable.
     *
     * @param  list<string>  $permissionKeys
     */
    public function update(AdminRole $role, string $nameEn, string $nameAr, array $permissionKeys): AdminRole
    {
        if ($role->isSuperAdmin()) {
            throw AdminOperationDenied::systemRole();
        }

        return DB::transaction(function () use ($role, $nameEn, $nameAr, $permissionKeys): AdminRole {
            $old = $role->permissions()->pluck('key')->sort()->values()->all();

            $role->update(['name_en' => $nameEn, 'name_ar' => $nameAr]);
            $role->permissions()->sync($this->ids($permissionKeys));

            $this->audit->handle('admin_role_updated', 'admin_role', $role->id, ['from' => $old, 'to' => collect($this->clean($permissionKeys))->sort()->values()->all()]);

            return $role->refresh();
        });
    }

    public function delete(AdminRole $role): void
    {
        if ($role->is_system) {
            throw AdminOperationDenied::systemRole();
        }

        $users = $role->users()->count();

        if ($users > 0) {
            throw AdminOperationDenied::roleInUse($users);
        }

        DB::transaction(function () use ($role): void {
            $this->audit->handle('admin_role_deleted', 'admin_role', $role->id, ['key' => $role->key]);
            $role->delete();
        });
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function clean(array $keys): array
    {
        return AdminPermission::query()->whereIn('key', $keys)->pluck('key')->all();
    }

    /**
     * @param  list<string>  $keys
     * @return list<int>
     */
    private function ids(array $keys): array
    {
        return AdminPermission::query()->whereIn('key', $keys)->pluck('id')->all();
    }

    private function uniqueKey(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'role';
        $key = $base;

        for ($i = 2; AdminRole::where('key', $key)->exists(); $i++) {
            $key = "{$base}_{$i}";
        }

        return $key;
    }
}
