<?php

namespace Modules\Tenancy\Infrastructure;

use Illuminate\Support\Facades\DB;
use Modules\Tenancy\Domain\Models\Company;
use Modules\Tenancy\Domain\Models\Tenant;

/**
 * Holds the tenant (and active company) for the current request or job.
 * Must be set explicitly before tenant-owned data is accessed. On PostgreSQL the tenant id is
 * also pushed into the session setting app.tenant_id, which the row level security policies use.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    private ?Company $company = null;

    public function setTenant(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->syncDatabaseSetting($tenant?->getKey());

        if ($this->company !== null && $this->company->tenant_id !== $tenant?->getKey()) {
            $this->company = null;
        }
    }

    public function setCompany(?Company $company): void
    {
        $this->company = $company;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function tenantId(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function company(): ?Company
    {
        return $this->company;
    }

    public function companyId(): ?int
    {
        return $this->company?->getKey();
    }

    public function clear(): void
    {
        $this->tenant = null;
        $this->company = null;
        $this->syncDatabaseSetting(null);
    }

    private function syncDatabaseSetting(?int $tenantId): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("select set_config('app.tenant_id', ?, false)", [$tenantId === null ? '' : (string) $tenantId]);
        }
    }
}
