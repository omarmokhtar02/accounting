<?php

namespace Modules\Shared\Domain\Contracts;

/**
 * Starts the free trial for a newly registered tenant. Implemented by the Subscriptions module.
 */
interface TrialStarter
{
    public function startFor(int $tenantId): void;
}
