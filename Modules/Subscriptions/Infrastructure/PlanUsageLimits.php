<?php

namespace Modules\Subscriptions\Infrastructure;

use App\Models\User;
use Modules\Shared\Domain\Contracts\UsageLimits;
use Modules\Subscriptions\Application\SubscriptionAccess;
use Modules\Subscriptions\Domain\Exceptions\PlanLimitReached;
use Modules\Tenancy\Domain\Models\Company;

/**
 * Enforces the limits snapshotted on the tenant's subscriptions (the most generous across all its running
 * plans). A tenant without a subscription has no right to add anything.
 */
final class PlanUsageLimits implements UsageLimits
{
    public function __construct(private readonly SubscriptionAccess $access) {}

    public function assertCanAddUser(int $tenantId): void
    {
        $limits = $this->access->limitsFor($tenantId) ?? throw PlanLimitReached::noAccess();

        if ($limits['max_users'] !== null && User::query()->where('tenant_id', $tenantId)->count() >= $limits['max_users']) {
            throw PlanLimitReached::users($limits['max_users']);
        }
    }

    public function assertCanAddCompany(int $tenantId): void
    {
        $limits = $this->access->limitsFor($tenantId) ?? throw PlanLimitReached::noAccess();

        if ($limits['max_companies'] !== null
            && Company::withoutGlobalScopes()->where('tenant_id', $tenantId)->count() >= $limits['max_companies']) {
            throw PlanLimitReached::companies($limits['max_companies']);
        }
    }
}
