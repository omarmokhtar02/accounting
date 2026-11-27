<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('accounting_period_id')->nullable();
            $table->string('number', 40)->nullable();
            $table->date('entry_date');
            $table->string('description');
            $table->string('status', 20)->default('draft');
            $table->string('currency', 3);
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->unsignedBigInteger('reverses_id')->nullable();
            $table->unsignedBigInteger('reversed_by_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->unique(['company_id', 'idempotency_key']);
            $table->unique(['company_id', 'id']);
            $table->unique('reverses_id');
            $table->index(['company_id', 'entry_date']);
            $table->index(['source_type', 'source_id']);
            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign(['tenant_id', 'company_id'])->references(['tenant_id', 'id'])->on('companies')->cascadeOnDelete();
            $table->foreign(['company_id', 'accounting_period_id'])->references(['company_id', 'id'])->on('accounting_periods')->restrictOnDelete();
            $table->foreign(['company_id', 'reverses_id'])->references(['company_id', 'id'])->on('journal_entries')->restrictOnDelete();
            $table->foreign(['company_id', 'reversed_by_id'])->references(['company_id', 'id'])->on('journal_entries')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
