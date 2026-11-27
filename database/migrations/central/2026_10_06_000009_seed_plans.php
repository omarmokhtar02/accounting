<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration: starter plans. Prices are intentionally 0 (not priced yet): the platform admin
 * sets real prices, limits and trial length from the admin dashboard. Nothing here is policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('plans')->insertOrIgnore([
            ['code' => 'starter', 'name_en' => 'Starter', 'name_ar' => 'الأساسية', 'price_minor' => 0, 'currency' => 'USD',
                'interval' => 'month', 'trial_days' => null, 'max_users' => 3, 'max_companies' => 1, 'features' => null,
                'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'business', 'name_en' => 'Business', 'name_ar' => 'الأعمال', 'price_minor' => 0, 'currency' => 'USD',
                'interval' => 'month', 'trial_days' => null, 'max_users' => 10, 'max_companies' => 5, 'features' => null,
                'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'enterprise', 'name_en' => 'Enterprise', 'name_ar' => 'المؤسسات', 'price_minor' => 0, 'currency' => 'USD',
                'interval' => 'month', 'trial_days' => null, 'max_users' => null, 'max_companies' => null, 'features' => null,
                'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        DB::table('plans')->whereIn('code', ['starter', 'business', 'enterprise'])->delete();
    }
};
