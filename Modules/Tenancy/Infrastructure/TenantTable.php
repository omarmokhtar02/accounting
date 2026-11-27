<?php

namespace Modules\Tenancy\Infrastructure;

use Illuminate\Support\Facades\DB;

/**
 * Migration helper for tenant-owned tables. Pair the `tenantOwned()` Blueprint macro (columns and
 * composite foreign keys, registered by TenancyServiceProvider) with enableRowLevelSecurity().
 */
final class TenantTable
{
    /**
     * PostgreSQL only: forces row level security keyed on the session setting app.tenant_id.
     * No-op on other drivers (the application scope enforces the same rule there).
     */
    public static function enableRowLevelSecurity(string $table): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        DB::statement("CREATE POLICY tenant_isolation ON {$table}
            USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::bigint)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::bigint)");
    }

    public static function disableRowLevelSecurity(string $table): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
        DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
    }
}
