<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // debit/credit stay the company's base currency (the ledger); these keep what was actually transacted.
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->string('txn_currency', 3)->nullable()->after('credit');
            $table->unsignedBigInteger('txn_debit')->nullable()->after('txn_currency');
            $table->unsignedBigInteger('txn_credit')->nullable()->after('txn_debit');
            $table->decimal('exchange_rate', 20, 10)->nullable()->after('txn_credit');
        });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropColumn(['txn_currency', 'txn_debit', 'txn_credit', 'exchange_rate']);
        });
    }
};
