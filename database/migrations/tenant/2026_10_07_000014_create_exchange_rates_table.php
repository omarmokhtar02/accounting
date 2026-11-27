<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Tenancy\Infrastructure\TenantTable;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->tenantOwned();
            $table->string('currency_code', 3);
            $table->date('rate_date');
            // Company base-currency units per ONE unit of the foreign currency (1 USD = 48.5 EGP).
            $table->decimal('rate', 20, 10);
            $table->string('source', 20)->default('manual');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'currency_code', 'rate_date']);
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        TenantTable::enableRowLevelSecurity('exchange_rates');
    }

    public function down(): void
    {
        TenantTable::disableRowLevelSecurity('exchange_rates');
        Schema::dropIfExists('exchange_rates');
    }
};
