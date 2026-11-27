<?php

namespace Modules\Accounting\Application;

use Modules\Accounting\Domain\Models\DocumentSequence;
use Modules\Tenancy\Infrastructure\Exceptions\InvalidTenantContext;
use Modules\Tenancy\Infrastructure\TenantContext;

/**
 * Concurrency-safe numbering. The row is created with INSERT ... ON CONFLICT DO NOTHING (no
 * exception to abort a PostgreSQL transaction), then locked and incremented. Callers must run
 * inside the posting transaction so a rolled-back post does not burn a number.
 */
final class DocumentSequencer
{
    public function __construct(private readonly TenantContext $context) {}

    public function next(int $companyId, string $type, int $year, string $prefix): string
    {
        $tenantId = $this->context->tenantId() ?? throw InvalidTenantContext::missing();
        $key = ['company_id' => $companyId, 'type' => $type, 'year' => $year];

        DocumentSequence::query()->insertOrIgnore([
            ...$key,
            'tenant_id' => $tenantId,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = DocumentSequence::query()->where($key)->lockForUpdate()->firstOrFail();
        $next = $sequence->last_number + 1;

        DocumentSequence::query()->whereKey($sequence->id)->update(['last_number' => $next, 'updated_at' => now()]);

        return sprintf('%s-%d-%06d', $prefix, $year, $next);
    }
}
