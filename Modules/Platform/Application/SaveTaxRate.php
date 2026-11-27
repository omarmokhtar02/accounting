<?php

namespace Modules\Platform\Application;

use Carbon\CarbonInterface;
use Modules\Localization\Application\CountryRegistry;
use Modules\Shared\Domain\Rounding;
use Modules\Tax\Application\RemoveScheduledTaxRate;
use Modules\Tax\Application\ScheduleTaxRate;
use Modules\Tax\Domain\Exceptions\InvalidTaxRateChange;
use Modules\Tax\Domain\Models\TaxRateSchedule;
use Modules\Tax\Domain\ValueObjects\TaxCodeSeed;

/**
 * The platform admin's tax-rate operations: schedule a new effective-dated rate for a country's tax code
 * (today or in advance), or cancel one that has not taken effect. Every change is audited.
 */
final class SaveTaxRate
{
    public function __construct(
        private readonly CountryRegistry $registry,
        private readonly ScheduleTaxRate $schedule,
        private readonly RemoveScheduledTaxRate $remove,
        private readonly RecordPlatformAudit $audit,
    ) {}

    /**
     * @param  string  $percent  e.g. "14", "2.5", "0.05" (at most two decimals)
     */
    public function schedule(string $country, string $code, string $percent, CarbonInterface $effectiveFrom, ?string $note = null, ?int $adminId = null): TaxRateSchedule
    {
        $seed = $this->definition($country, $code);
        $basisPoints = $this->toBasisPoints($percent);

        $row = $this->schedule->handle($country, $seed, $basisPoints, $effectiveFrom, $note, $adminId);

        $this->audit->handle('tax_rate_scheduled', 'tax_rate', $row->id, [
            'country' => strtoupper($country), 'code' => $code, 'rate_bp' => $basisPoints, 'effective_from' => $row->effective_from->toDateString(), 'note' => $note,
        ], $adminId);

        return $row;
    }

    public function remove(TaxRateSchedule $row, ?int $adminId = null): void
    {
        $data = ['country' => $row->country_code, 'code' => $row->code, 'rate_bp' => $row->rate_bp, 'effective_from' => $row->effective_from->toDateString()];

        $this->remove->handle($row);

        $this->audit->handle('tax_rate_removed', 'tax_rate', $row->id, $data, $adminId);
    }

    /** The tax code as the country pack defines it (kind and names), so a first rate can be set for any code. */
    private function definition(string $country, string $code): TaxCodeSeed
    {
        if (! $this->registry->has($country)) {
            throw InvalidTaxRateChange::unknownCode($country, $code);
        }

        foreach ($this->registry->get($country)->taxCodes() as $seed) {
            if ($seed->code === $code) {
                return $seed;
            }
        }

        throw InvalidTaxRateChange::unknownCode($country, $code);
    }

    private function toBasisPoints(string $percent): int
    {
        if (! preg_match('/^\d{1,3}(\.\d{1,2})?$/', $percent)) {
            throw InvalidTaxRateChange::rateOutOfRange();
        }

        return Rounding::toInteger(bcmul($percent, '100', 2));
    }
}
