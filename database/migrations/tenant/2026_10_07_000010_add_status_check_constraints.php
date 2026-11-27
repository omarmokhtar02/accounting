<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CHECK constraints on enum-like string columns (PostgreSQL). A CHECK is used instead of a native
 * enum so a later migration can widen the allowed values by dropping and re-adding the constraint.
 * SQLite cannot add constraints to existing tables; the domain enums enforce the same values there.
 */
return new class extends Migration
{
    /** @var array<string, array{column: string, values: list<string>}> */
    private const CHECKS = [
        'fiscal_years_status_check' => ['table' => 'fiscal_years', 'column' => 'status', 'values' => ['open', 'closed']],
        'accounting_periods_status_check' => ['table' => 'accounting_periods', 'column' => 'status', 'values' => ['open', 'closed']],
        'accounts_type_check' => ['table' => 'accounts', 'column' => 'type', 'values' => ['asset', 'liability', 'equity', 'revenue', 'expense']],
        'accounts_status_check' => ['table' => 'accounts', 'column' => 'status', 'values' => ['active', 'inactive', 'archived']],
        'journal_entries_status_check' => ['table' => 'journal_entries', 'column' => 'status', 'values' => ['draft', 'posted', 'reversed']],
        'companies_status_check' => ['table' => 'companies', 'column' => 'status', 'values' => ['active', 'inactive']],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::CHECKS as $name => $check) {
            $values = implode(', ', array_map(fn (string $v) => "'{$v}'", $check['values']));
            DB::statement("ALTER TABLE {$check['table']} ADD CONSTRAINT {$name} CHECK ({$check['column']} IN ({$values}))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::CHECKS as $name => $check) {
            DB::statement("ALTER TABLE {$check['table']} DROP CONSTRAINT IF EXISTS {$name}");
        }
    }
};
