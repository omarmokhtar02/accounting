<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Every table that stores tenant data must be tenant-safe at the database level: tenant_id and
 * company_id columns plus the composite foreign key (tenant_id, company_id) -> companies(tenant_id, id),
 * so a row can never belong to a different tenant than its company. A new tenant table fails this
 * test until it uses $table->tenantOwned().
 */

/** Tables that carry tenant_id for a reason other than being company-owned business data. */
const NOT_COMPANY_OWNED = [
    'users',                    // belongs to a tenant, not a company (membership is the company_user pivot)
    'companies',                // the root: tenant_id + unique(tenant_id, id)
    'roles',                    // spatie team key
    'model_has_roles',          // spatie team key
    'model_has_permissions',    // spatie team key
    'subscriptions',            // platform billing record: one per tenant, read across tenants by the admin
];

function tenantTables(): array
{
    return collect(Schema::getTables())
        ->pluck('name')
        ->filter(fn (string $table) => Schema::hasColumn($table, 'tenant_id') && ! in_array($table, NOT_COMPANY_OWNED, true))
        ->values()
        ->all();
}

it('finds the tenant tables so this guard cannot pass vacuously', function () {
    expect(tenantTables())->toContain('accounts', 'journal_entries', 'journal_lines', 'fiscal_years', 'account_mappings', 'audit_logs');
});

it('gives every tenant table a company_id and the composite tenant/company foreign key', function () {
    foreach (tenantTables() as $table) {
        expect(Schema::hasColumn($table, 'company_id'))->toBeTrue("{$table} has tenant_id but no company_id");

        $composite = collect(Schema::getForeignKeys($table))->contains(
            fn (array $fk) => $fk['foreign_table'] === 'companies'
                && $fk['columns'] === ['tenant_id', 'company_id']
                && $fk['foreign_columns'] === ['tenant_id', 'id'],
        );

        expect($composite)->toBeTrue("{$table} lacks the composite FK (tenant_id, company_id) -> companies(tenant_id, id). Use \$table->tenantOwned().");
    }
});

it('indexes company_id on every tenant table for per-company queries', function () {
    foreach (tenantTables() as $table) {
        $indexed = collect(Schema::getIndexes($table))->contains(fn (array $index) => ($index['columns'][0] ?? null) === 'company_id')
            || collect(Schema::getForeignKeys($table))->contains(fn (array $fk) => in_array('company_id', $fk['columns'], true));

        expect($indexed)->toBeTrue("{$table} has no index starting with company_id");
    }
});

it('enables forced row level security on every tenant table (PostgreSQL)', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Row level security needs PostgreSQL; run this in the Postgres CI job.');
    }

    foreach ([...tenantTables(), 'companies'] as $table) {
        $row = DB::selectOne('select relrowsecurity, relforcerowsecurity from pg_class where relname = ?', [$table]);

        expect($row->relrowsecurity && $row->relforcerowsecurity)->toBeTrue("{$table} has no forced row level security");
    }
});

it('keeps money columns as integers, never decimals or floats', function () {
    foreach (['journal_lines' => ['debit', 'credit']] as $table => $columns) {
        foreach ($columns as $column) {
            $type = strtolower(Schema::getColumnType($table, $column));

            expect($type)->toBeIn(['integer', 'bigint', 'int8', 'int']);
        }
    }
});
