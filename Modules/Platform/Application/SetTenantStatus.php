<?php

namespace Modules\Platform\Application;

use Illuminate\Support\Facades\DB;
use Modules\Tenancy\Domain\Models\Tenant;

final class SetTenantStatus
{
    public function __construct(private readonly RecordPlatformAudit $audit) {}

    /**
     * Suspends or reactivates a whole tenant. A suspended tenant cannot sign in to the app at all
     * (the tenant middleware rejects any status other than "active"); its data is untouched.
     */
    public function suspend(Tenant $tenant, ?string $reason = null): Tenant
    {
        return $this->change($tenant, 'suspended', 'tenant_suspended', $reason);
    }

    public function activate(Tenant $tenant): Tenant
    {
        return $this->change($tenant, 'active', 'tenant_activated', null);
    }

    private function change(Tenant $tenant, string $status, string $action, ?string $reason): Tenant
    {
        return DB::transaction(function () use ($tenant, $status, $action, $reason): Tenant {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            $previous = $tenant->status;

            $tenant->update(['status' => $status]);

            $this->audit->handle($action, 'tenant', $tenant->id, ['from' => $previous, 'to' => $status, 'reason' => $reason]);

            return $tenant;
        });
    }
}
