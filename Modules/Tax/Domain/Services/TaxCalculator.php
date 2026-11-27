<?php

namespace Modules\Tax\Domain\Services;

use Modules\Shared\Domain\Money;
use Modules\Shared\Domain\Rounding;
use Modules\Tax\Domain\ValueObjects\TaxRate;

/**
 * Deterministic tax arithmetic on integer minor units: bcmath, one rounding rule (half away from zero),
 * no floats. Tax is rounded per amount you give it, so call it per line or per document consistently.
 */
final class TaxCalculator
{
    /** Tax charged on a net amount (tax-exclusive pricing). */
    public function onNet(Money $net, TaxRate $rate): Money
    {
        $value = bcdiv(bcmul((string) $net->minor, (string) $rate->basisPoints, 0), '10000', Rounding::SCALE);

        return new Money(Rounding::toInteger($value), $net->currency);
    }

    /** The tax contained in a gross amount (tax-inclusive pricing). */
    public function insideGross(Money $gross, TaxRate $rate): Money
    {
        $value = bcdiv(bcmul((string) $gross->minor, (string) $rate->basisPoints, 0), (string) (10000 + $rate->basisPoints), Rounding::SCALE);

        return new Money(Rounding::toInteger($value), $gross->currency);
    }

    /** Splits a gross amount into net and tax so that net + tax is always exactly the gross. */
    public function splitGross(Money $gross, TaxRate $rate): array
    {
        $tax = $this->insideGross($gross, $rate);

        return ['net' => $gross->subtract($tax), 'tax' => $tax];
    }
}
