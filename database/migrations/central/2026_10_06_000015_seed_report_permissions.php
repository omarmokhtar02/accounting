<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration: tenant permissions for the financial reports. reports.export is separate from
 * reports.view so a company can let people read reports without letting them take the data out.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['reports.view', 'reports.export'];

    public function up(): void
    {
        $now = now();

        foreach (self::PERMISSIONS as $name) {
            DB::table(config('permission.table_names.permissions'))->insertOrIgnore([
                'name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table(config('permission.table_names.permissions'))->whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->delete();
    }
};
