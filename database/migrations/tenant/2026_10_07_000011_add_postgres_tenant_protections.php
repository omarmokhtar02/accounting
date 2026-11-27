<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL-only defence in depth: row level security keyed on the session setting
 * app.tenant_id (set by TenantContext), plus triggers that make the ledger append-only.
 * Other drivers skip this migration; the same rules are enforced in the application.
 *
 * Note: RLS does not apply to superusers or table owners without FORCE, so production must
 * connect with a non-superuser application role.
 */
return new class extends Migration
{
    private const TENANT_TABLES = [
        'companies', 'fiscal_years', 'accounting_periods', 'accounts',
        'journal_entries', 'journal_lines', 'audit_logs', 'document_sequences',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TENANT_TABLES as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("CREATE POLICY tenant_isolation ON {$table}
                USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::bigint)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::bigint)");
        }

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION forbid_audit_log_changes() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'audit_logs is append-only';
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS audit_logs_append_only ON audit_logs;
CREATE TRIGGER audit_logs_append_only
    BEFORE UPDATE OR DELETE ON audit_logs
    FOR EACH ROW EXECUTE FUNCTION forbid_audit_log_changes();

CREATE OR REPLACE FUNCTION guard_posted_journal_entry() RETURNS trigger AS $$
BEGIN
    IF OLD.status <> 'draft' THEN
        IF TG_OP = 'DELETE' THEN
            RAISE EXCEPTION 'posted journal entries cannot be deleted';
        END IF;
        IF NEW.tenant_id IS DISTINCT FROM OLD.tenant_id
            OR NEW.company_id IS DISTINCT FROM OLD.company_id
            OR NEW.number IS DISTINCT FROM OLD.number
            OR NEW.entry_date IS DISTINCT FROM OLD.entry_date
            OR NEW.description IS DISTINCT FROM OLD.description
            OR NEW.currency IS DISTINCT FROM OLD.currency
            OR NEW.created_by IS DISTINCT FROM OLD.created_by
            OR NEW.posted_by IS DISTINCT FROM OLD.posted_by
            OR NEW.posted_at IS DISTINCT FROM OLD.posted_at
            OR NEW.reverses_id IS DISTINCT FROM OLD.reverses_id THEN
            RAISE EXCEPTION 'posted journal entries are immutable';
        END IF;
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS journal_entries_guard ON journal_entries;
CREATE TRIGGER journal_entries_guard
    BEFORE UPDATE OR DELETE ON journal_entries
    FOR EACH ROW EXECUTE FUNCTION guard_posted_journal_entry();

CREATE OR REPLACE FUNCTION guard_posted_journal_line() RETURNS trigger AS $$
DECLARE
    entry_status text;
BEGIN
    SELECT status INTO entry_status FROM journal_entries WHERE id = OLD.journal_entry_id;
    IF entry_status IS NOT NULL AND entry_status <> 'draft' THEN
        RAISE EXCEPTION 'lines of a posted journal entry are immutable';
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS journal_lines_guard ON journal_lines;
CREATE TRIGGER journal_lines_guard
    BEFORE UPDATE OR DELETE ON journal_lines
    FOR EACH ROW EXECUTE FUNCTION guard_posted_journal_line();
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS journal_lines_guard ON journal_lines;
DROP TRIGGER IF EXISTS journal_entries_guard ON journal_entries;
DROP TRIGGER IF EXISTS audit_logs_append_only ON audit_logs;
DROP FUNCTION IF EXISTS guard_posted_journal_line();
DROP FUNCTION IF EXISTS guard_posted_journal_entry();
DROP FUNCTION IF EXISTS forbid_audit_log_changes();
SQL);

        foreach (self::TENANT_TABLES as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
