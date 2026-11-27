<?php

namespace Modules\Tax\Application;

use Modules\Tax\Domain\Exceptions\InvalidTaxRateChange;
use Modules\Tax\Domain\Models\TaxRateSchedule;

final class RemoveScheduledTaxRate
{
    public function __construct(private readonly TaxRateResolver $resolver) {}

    /**
     * Cancels a rate change that has not taken effect yet. A rate already in force (or in the past) is
     * history and can never be removed.
     */
    public function handle(TaxRateSchedule $schedule): void
    {
        if ($schedule->effective_from->toDateString() <= now()->toDateString()) {
            throw InvalidTaxRateChange::alreadyInForce();
        }

        $schedule->delete();
        $this->resolver->forget();
    }
}
