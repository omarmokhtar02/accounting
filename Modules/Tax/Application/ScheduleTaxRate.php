<?php

namespace Modules\Tax\Application;

use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Tax\Domain\Exceptions\InvalidTaxRateChange;
use Modules\Tax\Domain\Models\TaxRateSchedule;
use Modules\Tax\Domain\ValueObjects\TaxCodeSeed;

final class ScheduleTaxRate
{
    public function __construct(private readonly TaxRateResolver $resolver) {}

    /**
     * Adds a rate that takes effect on $effectiveFrom (today or later: past dates are refused so already
     * posted documents can never be silently re-rated). Use it for today's law change or to schedule a
     * future one in advance.
     */
    public function handle(string $country, TaxCodeSeed $code, int $rateBasisPoints, CarbonInterface $effectiveFrom, ?string $note = null, ?int $adminId = null): TaxRateSchedule
    {
        if ($rateBasisPoints < 0 || $rateBasisPoints > 10000) {
            throw InvalidTaxRateChange::rateOutOfRange();
        }

        $date = $effectiveFrom->toDateString();

        if ($date < now()->toDateString()) {
            throw InvalidTaxRateChange::inThePast();
        }

        $country = strtoupper($country);

        if (TaxRateSchedule::query()->where('country_code', $country)->where('code', $code->code)->whereDate('effective_from', $date)->exists()) {
            throw InvalidTaxRateChange::duplicateDate($date);
        }

        try {
            $schedule = TaxRateSchedule::create([
                'country_code' => $country, 'code' => $code->code, 'kind' => $code->kind, 'name_en' => $code->nameEn, 'name_ar' => $code->nameAr,
                'rate_bp' => $rateBasisPoints, 'effective_from' => $date, 'note' => $note, 'created_by' => $adminId,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw InvalidTaxRateChange::duplicateDate($date);
        }

        $this->resolver->forget();

        return $schedule;
    }
}
