<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Tenancy\Infrastructure\TenantTable;

return new class extends Migration
{
    public function up(): void
    {
        // The company's tax codes (what documents will reference). The RATE is not stored here: it is
        // looked up by date from the country's tax_rate_schedules, so a law change needs no company edit.
        Schema::create('tax_codes', function (Blueprint $table) {
            $table->id();
            $table->tenantOwned();
            $table->string('code', 40);
            $table->string('kind', 20);
            $table->string('name_en');
            $table->string('name_ar');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        TenantTable::enableRowLevelSecurity('tax_codes');
    }

    public function down(): void
    {
        TenantTable::disableRowLevelSecurity('tax_codes');
        Schema::dropIfExists('tax_codes');
    }
};
