<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Tenancy\Infrastructure\TenantTable;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_mappings', function (Blueprint $table) {
            $table->id();
            $table->tenantOwned();
            $table->string('role', 40);
            $table->unsignedBigInteger('account_id');
            $table->timestamps();

            // One account per role per company.
            $table->unique(['company_id', 'role']);
            $table->foreign(['company_id', 'account_id'])->references(['company_id', 'id'])->on('accounts')->restrictOnDelete();
        });

        TenantTable::enableRowLevelSecurity('account_mappings');
    }

    public function down(): void
    {
        TenantTable::disableRowLevelSecurity('account_mappings');
        Schema::dropIfExists('account_mappings');
    }
};
