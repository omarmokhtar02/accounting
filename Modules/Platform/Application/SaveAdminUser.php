<?php

namespace Modules\Platform\Application;

use Illuminate\Support\Facades\DB;
use Modules\Platform\Domain\Exceptions\AdminOperationDenied;
use Modules\Platform\Domain\Models\AdminRole;
use Modules\Platform\Domain\Models\AdminUser;

final class SaveAdminUser
{
    public const MIN_PASSWORD_LENGTH = 12;

    public function __construct(
        private readonly AdminGuardrails $guardrails,
        private readonly RecordPlatformAudit $audit,
    ) {}

    public function create(string $name, string $email, string $password, AdminRole $role, string $locale = 'en'): AdminUser
    {
        $this->assertStrongPassword($password);

        return DB::transaction(function () use ($name, $email, $password, $role, $locale): AdminUser {
            $admin = AdminUser::create([
                'name' => $name, 'email' => strtolower($email), 'password' => $password,
                'admin_role_id' => $role->id, 'locale' => $locale, 'is_active' => true,
            ]);

            $this->audit->handle('admin_created', 'admin_user', $admin->id, ['email' => $admin->email, 'role' => $role->key]);

            return $admin;
        });
    }

    /**
     * Updates an admin. A null password keeps the current one. Demoting or deactivating the last active
     * super admin, or deactivating yourself, is refused.
     */
    public function update(AdminUser $admin, string $name, AdminRole $role, bool $active, ?string $password = null, ?string $locale = null, ?int $actingAdminId = null): AdminUser
    {
        if ($password !== null && $password !== '') {
            $this->assertStrongPassword($password);
        }

        return DB::transaction(function () use ($admin, $name, $role, $active, $password, $locale, $actingAdminId): AdminUser {
            $admin = AdminUser::query()->lockForUpdate()->findOrFail($admin->id);
            $loses = ($admin->is_active && ! $active) || ($admin->isSuperAdmin() && ! $role->isSuperAdmin());

            if ($admin->is_active && ! $active) {
                $this->guardrails->assertNotSelf($admin, $actingAdminId);
            }

            if ($loses) {
                $this->guardrails->assertNotRemovingLastSuperAdmin($admin);
            }

            $old = ['role' => $admin->role->key, 'is_active' => $admin->is_active];

            $attributes = ['name' => $name, 'admin_role_id' => $role->id, 'is_active' => $active];

            if ($locale !== null) {
                $attributes['locale'] = $locale;
            }

            if ($password !== null && $password !== '') {
                $attributes['password'] = $password;
            }

            $admin->update($attributes);

            $this->audit->handle('admin_updated', 'admin_user', $admin->id, [
                'from' => $old, 'to' => ['role' => $role->key, 'is_active' => $active], 'password_changed' => isset($attributes['password']),
            ]);

            return $admin->refresh();
        });
    }

    private function assertStrongPassword(string $password): void
    {
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw AdminOperationDenied::weakPassword();
        }
    }
}
