<?php

namespace Modules\Subscriptions\Application;

use Illuminate\Support\Facades\DB;
use Modules\Subscriptions\Domain\Models\Plan;

final class GrantAllPlans
{
    public function __construct(private readonly GrantPlan $grant) {}

    /**
     * Gives a tenant every active plan at once ("access to all plans").
     *
     * @return int number of plans granted
     */
    public function handle(int $tenantId, int $periods = 1, ?int $adminId = null): int
    {
        return DB::transaction(function () use ($tenantId, $periods, $adminId): int {
            $plans = Plan::query()->where('is_active', true)->orderBy('sort_order')->get();

            foreach ($plans as $plan) {
                $this->grant->handle($tenantId, $plan, $periods, $adminId);
            }

            return $plans->count();
        });
    }
}
