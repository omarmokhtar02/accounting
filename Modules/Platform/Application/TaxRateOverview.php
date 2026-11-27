<?php

namespace Modules\Platform\Application;

use Modules\Localization\Application\CountryRegistry;
use Modules\Tax\Application\TaxRateResolver;
use Modules\Tax\Domain\Exceptions\TaxRateNotConfigured;

/**
 * Read model for the admin tax-rates screen: every tax code of every installed country with the rate in
 * force today, the next scheduled change and the full history.
 */
final class TaxRateOverview
{
    public function __construct(
        private readonly CountryRegistry $registry,
        private readonly TaxRateResolver $resolver,
    ) {}

    /**
     * @return array<string, array{country: string, name: string, codes: list<array<string, mixed>>}>
     */
    public function build(): array
    {
        $overview = [];

        foreach ($this->registry->all() as $country => $pack) {
            $codes = [];

            foreach ($pack->taxCodes() as $seed) {
                try {
                    $current = $this->resolver->rateFor($country, $seed->code, now());
                } catch (TaxRateNotConfigured) {
                    $current = null;
                }

                $codes[] = [
                    'code' => $seed->code,
                    'kind' => $seed->kind,
                    'name' => app()->getLocale() === 'ar' ? $seed->nameAr : $seed->nameEn,
                    'current' => $current,
                    'next' => $this->resolver->nextChange($country, $seed->code, now()),
                    'history' => $this->resolver->history($country, $seed->code),
                ];
            }

            $overview[$country] = [
                'country' => $country,
                'name' => app()->getLocale() === 'ar' ? $pack->profile()->nameAr : $pack->profile()->nameEn,
                'codes' => $codes,
            ];
        }

        return $overview;
    }
}
