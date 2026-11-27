<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Effective-dated tax rates per country, edited by the platform admin. A rate applies from
        // effective_from until the next row for the same country and code. Never hard-coded in the app.
        Schema::create('tax_rate_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 2);
            $table->string('code', 40);
            $table->string('kind', 20);
            $table->string('name_en');
            $table->string('name_ar');
            // Basis points: 1% = 100, 14% = 1400, 0.5% = 50. Integer, so no float ever touches a rate.
            $table->unsignedInteger('rate_bp');
            $table->date('effective_from');
            $table->string('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['country_code', 'code', 'effective_from']);
            $table->index(['country_code', 'code']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tax_rate_schedules ADD CONSTRAINT tax_rate_bp_range CHECK (rate_bp BETWEEN 0 AND 10000)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rate_schedules');
    }
};
