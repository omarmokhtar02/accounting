<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('status', 20);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            // Snapshot at subscribe time: later edits to the plan do not silently change a running subscription.
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->string('currency', 3);
            $table->string('interval', 10)->default('month');
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_companies')->nullable();
            $table->timestamps();

            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->index('trial_ends_at');
            $table->index('current_period_ends_at');
        });

        // A tenant has at most one live (trialing or active) subscription at a time.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            // MySQL has no partial indexes: index a column that is NULL unless live, since NULLs never collide.
            // Invisible so `select *` skips it and models never try to write it back.
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->unsignedBigInteger('live_tenant_id')->nullable()->virtualAs("case when status in ('trialing', 'active') then tenant_id end")->invisible();
                $table->unique('live_tenant_id', 'subscriptions_one_live_per_tenant');
            });
        } else {
            // Partial unique index: supported by both SQLite and PostgreSQL.
            DB::statement("CREATE UNIQUE INDEX subscriptions_one_live_per_tenant ON subscriptions (tenant_id) WHERE status IN ('trialing', 'active')");
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_status_check CHECK (status IN ('trialing', 'active', 'expired', 'canceled'))");
        }

        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->json('data')->nullable();
            // The platform admin who did it; null for system actions (registration, scheduled expiry).
            $table->unsignedBigInteger('admin_user_id')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['subscription_id', 'occurred_at']);
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('method', 30);
            $table->string('reference', 100)->nullable();
            $table->timestamp('paid_at');
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(['method', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('subscriptions');
    }
};
