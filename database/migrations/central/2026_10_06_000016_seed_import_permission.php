<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration: the permission to upload spreadsheets (chart of accounts, journals, exchange rates).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table(config('permission.table_names.permissions'))->insertOrIgnore([
            'name' => 'imports.run', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table(config('permission.table_names.permissions'))->where('name', 'imports.run')->where('guard_name', 'web')->delete();
    }
};
