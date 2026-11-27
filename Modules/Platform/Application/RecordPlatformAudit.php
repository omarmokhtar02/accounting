<?php

namespace Modules\Platform\Application;

use Modules\Platform\Domain\Models\PlatformAuditLog;

/**
 * Append-only trail of what platform admins did: suspensions, trial extensions, plan and rate changes,
 * role edits. The actor defaults to the logged-in admin.
 */
final class RecordPlatformAudit
{
    /** @param array<string, mixed> $data */
    public function handle(string $action, string $entityType, ?int $entityId = null, array $data = [], ?int $adminId = null): PlatformAuditLog
    {
        return PlatformAuditLog::create([
            'admin_user_id' => $adminId ?? auth('admin')->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'data' => $data === [] ? null : $data,
            'occurred_at' => now(),
        ]);
    }
}
