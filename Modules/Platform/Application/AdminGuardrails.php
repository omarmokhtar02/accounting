<?php

namespace Modules\Platform\Application;

use Modules\Platform\Domain\Exceptions\AdminOperationDenied;
use Modules\Platform\Domain\Models\AdminRole;
use Modules\Platform\Domain\Models\AdminUser;

/**
 * Rules that keep the platform administrable: there is always at least one active super admin, and an
 * admin cannot lock themselves out.
 */
final class AdminGuardrails
{
    /**
     * Call before a change that would stop $target from being an active super admin
     * (deactivating, demoting, deleting).
     */
    public function assertNotRemovingLastSuperAdmin(AdminUser $target): void
    {
        if (! $target->isSuperAdmin() || ! $target->is_active) {
            return;
        }

        $others = AdminUser::query()
            ->whereKeyNot($target->id)
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('key', AdminRole::SUPER_ADMIN))
            ->count();

        if ($others === 0) {
            throw AdminOperationDenied::lastSuperAdmin();
        }
    }

    public function assertNotSelf(AdminUser $target, ?int $actingAdminId): void
    {
        if ($actingAdminId !== null && $target->id === $actingAdminId) {
            throw AdminOperationDenied::cannotDeactivateSelf();
        }
    }
}
