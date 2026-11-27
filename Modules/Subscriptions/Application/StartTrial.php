<?php

namespace Modules\Subscriptions\Application;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Application\PlatformSettings;
use Modules\Shared\Domain\Contracts\TrialStarter;
use Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use Modules\Subscriptions\Domain\Exceptions\InvalidSubscriptionChange;
use Modules\Subscriptions\Domain\Models\Plan;
use Modules\Subscriptions\Domain\Models\Subscription;

final class StartTrial implements TrialStarter
{
    use RecordsSubscriptionEvents;

    public function __construct(private readonly PlatformSettings $settings) {}

    /**
     * Starts the free trial for a new tenant. The length comes from the plan's own trial_days, or the
     * platform default (setting trial.days, 14 unless the admin changed it). Idempotent: a tenant that
     * already has a live subscription is left alone.
     */
    public function startFor(int $tenantId): void
    {
        DB::transaction(function () use ($tenantId): void {
            if (Subscription::query()->where('tenant_id', $tenantId)->whereIn('status', ['trialing', 'active'])->exists()) {
                return;
            }

            $plan = Plan::query()->where('code', $this->settings->string('trial.plan_code'))->where('is_active', true)->first()
                ?? Plan::query()->where('is_active', true)->orderBy('sort_order')->first()
                ?? throw InvalidSubscriptionChange::planInactive();

            $days = $plan->trial_days ?? $this->settings->int('trial.days');

            $subscription = Subscription::create([
                'tenant_id' => $tenantId,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => now()->addDays($days),
                'price_minor' => $plan->price_minor,
                'currency' => $plan->currency,
                'interval' => $plan->interval,
                'max_users' => $plan->max_users,
                'max_companies' => $plan->max_companies,
            ]);

            $this->recordEvent($subscription, 'trial_started', ['days' => $days, 'plan' => $plan->code]);
        });
    }
}
