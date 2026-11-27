<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('country_code', 2);
            // Which version of the country pack (chart of accounts, tax rules) the company was created from.
            $table->string('country_pack_version', 20)->nullable();
            $table->string('base_currency', 3);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('tenant_id');
            // Target for composite foreign keys: guarantees a child's tenant_id matches its company's.
            $table->unique(['tenant_id', 'id']);
            $table->foreign('base_currency')->references('code')->on('currencies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
