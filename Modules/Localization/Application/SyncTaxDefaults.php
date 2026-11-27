<?php

namespace Modules\Localization\Application;

use Modules\Tax\Application\TaxRateResolver;
use Modules\Tax\Domain\Models\TaxRateSchedule;

final class SyncTaxDefaults
{
    public function __construct(
        private readonly CountryRegistry $registry,
        private readonly TaxRateResolver $resolver,
    ) {}

    /**
     * Inserts each country pack's default tax rates that are not in the database yet. It NEVER overwrites
     * or removes a row, so rates the platform admin changed (or dates they added) are always respected.
     * Safe to run on every deploy.
     *
     * @return int number of rates inserted
     */
    public function handle(): int
    {
        $inserted = 0;

        foreach ($this->registry->all() as $country => $pack) {
            foreach ($pack->taxCodes() as $seed) {
                foreach ($seed->defaultRates as $date => $basisPoints) {
                    $row = TaxRateSchedule::query()->firstOrCreate(
                        ['country_code' => $country, 'code' => $seed->code, 'effective_from' => $date],
                        ['kind' => $seed->kind, 'name_en' => $seed->nameEn, 'name_ar' => $seed->nameAr, 'rate_bp' => $basisPoints, 'note' => 'default'],
                    );

                    $inserted += $row->wasRecentlyCreated ? 1 : 0;
                }
            }
        }

        $this->resolver->forget();

        return $inserted;
    }
}
