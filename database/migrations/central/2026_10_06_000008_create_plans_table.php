<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name_en');
            $table->string('name_ar');
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            // Price per billing interval in the minor units of $currency. Admin editable, 0 = not priced yet.
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->string('currency', 3);
            $table->string('interval', 10)->default('month');
            // null = use the platform default (setting trial.days); 0 = no trial for this plan.
            $table->unsignedSmallInteger('trial_days')->nullable();
            // null = unlimited.
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_companies')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
