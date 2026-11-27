<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('debit')->default(0);
            $table->unsignedBigInteger('credit')->default(0);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'account_id']);
            $table->index('journal_entry_id');
            $table->foreign(['tenant_id', 'company_id'])->references(['tenant_id', 'id'])->on('companies')->cascadeOnDelete();
            $table->foreign(['company_id', 'journal_entry_id'])->references(['company_id', 'id'])->on('journal_entries')->cascadeOnDelete();
            $table->foreign(['company_id', 'account_id'])->references(['company_id', 'id'])->on('accounts')->restrictOnDelete();
        });

        // SQLite cannot add a CHECK to an existing table; the domain enforces the same rule there.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_one_sided CHECK ((debit = 0 AND credit > 0) OR (credit = 0 AND debit > 0))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
