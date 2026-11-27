<?php

namespace Modules\Shared\Domain\Contracts;

use Modules\Subscriptions\Domain\Exceptions\PlanLimitReached;

/**
 * Plan limits the rest of the system must respect. Implemented by the Subscriptions module so the
 * Identity and Localization modules can enforce "max users / max companies" without knowing about plans.
 */
interface UsageLimits
{
    /** @throws PlanLimitReached */
    public function assertCanAddUser(int $tenantId): void;

    /** @throws PlanLimitReached */
    public function assertCanAddCompany(int $tenantId): void;
}
