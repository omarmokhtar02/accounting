<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration: ISO 4217 currencies relevant to the launch markets (Egypt, Saudi Arabia, Kuwait)
 * and the wider region. Add more currencies with a new migration; do not edit this list.
 */
return new class extends Migration
{
    /** code => [english, arabic, symbol, decimals] */
    private const CURRENCIES = [
        'EGP' => ['Egyptian Pound', 'جنيه مصري', 'E£', 2],
        'SAR' => ['Saudi Riyal', 'ريال سعودي', 'ر.س', 2],
        'KWD' => ['Kuwaiti Dinar', 'دينار كويتي', 'د.ك', 3],
        'AED' => ['UAE Dirham', 'درهم إماراتي', 'د.إ', 2],
        'BHD' => ['Bahraini Dinar', 'دينار بحريني', 'د.ب', 3],
        'OMR' => ['Omani Rial', 'ريال عماني', 'ر.ع', 3],
        'QAR' => ['Qatari Riyal', 'ريال قطري', 'ر.ق', 2],
        'JOD' => ['Jordanian Dinar', 'دينار أردني', 'د.أ', 3],
        'LBP' => ['Lebanese Pound', 'ليرة لبنانية', 'ل.ل', 2],
        'IQD' => ['Iraqi Dinar', 'دينار عراقي', 'د.ع', 3],
        'LYD' => ['Libyan Dinar', 'دينار ليبي', 'د.ل', 3],
        'TND' => ['Tunisian Dinar', 'دينار تونسي', 'د.ت', 3],
        'MAD' => ['Moroccan Dirham', 'درهم مغربي', 'د.م', 2],
        'DZD' => ['Algerian Dinar', 'دينار جزائري', 'د.ج', 2],
        'SDG' => ['Sudanese Pound', 'جنيه سوداني', 'ج.س', 2],
        'USD' => ['US Dollar', 'دولار أمريكي', '$', 2],
        'EUR' => ['Euro', 'يورو', '€', 2],
        'GBP' => ['Pound Sterling', 'جنيه إسترليني', '£', 2],
        'CHF' => ['Swiss Franc', 'فرنك سويسري', 'CHF', 2],
        'JPY' => ['Japanese Yen', 'ين ياباني', '¥', 0],
        'CNY' => ['Chinese Yuan', 'يوان صيني', '¥', 2],
        'INR' => ['Indian Rupee', 'روبية هندية', '₹', 2],
        'TRY' => ['Turkish Lira', 'ليرة تركية', '₺', 2],
        'CAD' => ['Canadian Dollar', 'دولار كندي', 'C$', 2],
        'AUD' => ['Australian Dollar', 'دولار أسترالي', 'A$', 2],
    ];

    public function up(): void
    {
        $now = now();

        DB::table('currencies')->insertOrIgnore(array_map(
            fn (string $code, array $c) => [
                'code' => $code, 'name_en' => $c[0], 'name_ar' => $c[1], 'symbol' => $c[2],
                'decimals' => $c[3], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            array_keys(self::CURRENCIES),
            self::CURRENCIES,
        ));
    }

    public function down(): void
    {
        DB::table('currencies')->whereIn('code', array_keys(self::CURRENCIES))->delete();
    }
};
