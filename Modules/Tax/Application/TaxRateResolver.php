<?php

namespace Modules\Tax\Application;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Tax\Domain\Exceptions\TaxRateNotConfigured;
use Modules\Tax\Domain\Models\TaxRateSchedule;
use Modules\Tax\Domain\ValueObjects\TaxRate;

/**
 * Answers "what rate applies to this tax code, in this country, on this date?". Rates are data edited
 * by the platform admin, never constants: when a law changes, a new dated row is added and every
 * document from that date on picks it up. Documents already posted keep the rate they were posted with.
 */
final class TaxRateResolver
{
    /** @var array<string, Collection<int, TaxRateSchedule>> */
    private array $cache = [];

    public function rateFor(string $country, string $code, CarbonInterface $date): TaxRate
    {
        $day = $date->toDateString();

        $row = $this->schedule($country, $code)->first(fn (TaxRateSchedule $s) => $s->effective_from->toDateString() <= $day)
            ?? throw TaxRateNotConfigured::for($country, $code, $day);

        return $row->toRate();
    }

    public function hasRate(string $country, string $code, CarbonInterface $date): bool
    {
        try {
            $this->rateFor($country, $code, $date);

            return true;
        } catch (TaxRateNotConfigured) {
            return false;
        }
    }

    /**
     * The next rate change after $date, or null when none is scheduled.
     */
    public function nextChange(string $country, string $code, CarbonInterface $date): ?TaxRateSchedule
    {
        $day = $date->toDateString();

        return $this->schedule($country, $code)->reverse()->first(fn (TaxRateSchedule $s) => $s->effective_from->toDateString() > $day);
    }

    /**
     * Newest first.
     *
     * @return Collection<int, TaxRateSchedule>
     */
    public function history(string $country, string $code): Collection
    {
        return $this->schedule($country, $code);
    }

    public function forget(): void
    {
        $this->cache = [];
    }

    /** @return Collection<int, TaxRateSchedule> */
    private function schedule(string $country, string $code): Collection
    {
        return $this->cache[strtoupper($country).'|'.$code] ??= TaxRateSchedule::query()
            ->where('country_code', strtoupper($country))->where('code', $code)
            ->orderByDesc('effective_from')->get();
    }
}
