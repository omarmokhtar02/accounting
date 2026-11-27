<?php

namespace Modules\Tax\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A tax rate in basis points (1% = 100) that applied from $effectiveFrom ('YYYY-MM-DD').
 */
final readonly class TaxRate
{
    public function __construct(public int $basisPoints, public string $effectiveFrom)
    {
        if ($basisPoints < 0 || $basisPoints > 10000) {
            throw new InvalidArgumentException('A tax rate must be between 0% and 100%.');
        }
    }

    public function isZero(): bool
    {
        return $this->basisPoints === 0;
    }

    /** The rate as a percentage string for display, without trailing zeros: "14", "2.5", "0.05". */
    public function percent(): string
    {
        $whole = intdiv($this->basisPoints, 100);
        $fraction = str_pad((string) ($this->basisPoints % 100), 2, '0', STR_PAD_LEFT);

        return rtrim($whole.'.'.$fraction, '0.') === '' ? '0' : rtrim(rtrim($whole.'.'.$fraction, '0'), '.');
    }
}
