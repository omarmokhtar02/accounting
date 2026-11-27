<?php

namespace Modules\Tax\Domain\ValueObjects;

use InvalidArgumentException;
use Modules\Tax\Domain\Enums\TaxKind;

/**
 * A tax code a country pack offers, with optional DEFAULT rates (basis points by effective date). A code
 * with no default rate is valid: the platform admin must set its rate before it can be used.
 */
final readonly class TaxCodeSeed
{
    /**
     * @param  array<string, int>  $defaultRates  'YYYY-MM-DD' effective date => rate in basis points
     */
    public function __construct(
        public string $code,
        public TaxKind $kind,
        public string $nameEn,
        public string $nameAr,
        public array $defaultRates = [],
    ) {
        if (! preg_match('/^[A-Z][A-Z0-9_]{1,39}$/', $code)) {
            throw new InvalidArgumentException("Invalid tax code: {$code}");
        }

        foreach ($defaultRates as $date => $bp) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) || $bp < 0 || $bp > 10000) {
                throw new InvalidArgumentException("Invalid default rate for {$code}: {$date} => {$bp}");
            }
        }
    }
}
