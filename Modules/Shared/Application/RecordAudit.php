<?php

namespace Modules\Shared\Application;

use Modules\Shared\Domain\Models\AuditLog;
use Modules\Tenancy\Infrastructure\TenantContext;

final class RecordAudit
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function handle(string $entityType, int $entityId, string $action, ?array $old = null, ?array $new = null, ?string $reason = null): AuditLog
    {
        return AuditLog::create([
            'company_id' => $this->context->companyId(),
            'actor_id' => auth()->id(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
    }
}
