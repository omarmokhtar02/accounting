<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A company may hold several plans at once (or all of them): the "one live subscription per tenant" rule
 * becomes "one live subscription per tenant and plan". SQLite and PostgreSQL use a partial unique index;
 * MySQL uses the live_tenant_id generated column added with the subscriptions table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->usesGeneratedColumn()) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropUnique('subscriptions_one_live_per_tenant');
                $table->unique(['live_tenant_id', 'plan_id'], 'subscriptions_one_live_per_tenant_plan');
            });

            return;
        }

        DB::statement('DROP INDEX IF EXISTS subscriptions_one_live_per_tenant');
        DB::statement("CREATE UNIQUE INDEX subscriptions_one_live_per_tenant_plan ON subscriptions (tenant_id, plan_id) WHERE status IN ('trialing', 'active')");
    }

    public function down(): void
    {
        if ($this->usesGeneratedColumn()) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropUnique('subscriptions_one_live_per_tenant_plan');
                $table->unique('live_tenant_id', 'subscriptions_one_live_per_tenant');
            });

            return;
        }

        DB::statement('DROP INDEX IF EXISTS subscriptions_one_live_per_tenant_plan');
        DB::statement("CREATE UNIQUE INDEX subscriptions_one_live_per_tenant ON subscriptions (tenant_id) WHERE status IN ('trialing', 'active')");
    }

    private function usesGeneratedColumn(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
};
