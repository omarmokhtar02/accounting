<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration: adds the tenant permission tax.view (read-only look at the company's tax codes and
 * current rates). Tax rates themselves are platform-managed, so there is no tenant tax.manage.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table(config('permission.table_names.permissions'))->insertOrIgnore([
            'name' => 'tax.view', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table(config('permission.table_names.permissions'))->where('name', 'tax.view')->where('guard_name', 'web')->delete();
    }
};
