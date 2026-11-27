<?php

namespace Modules\Shared\Domain;

use InvalidArgumentException;

/**
 * An ISO 4217 currency and the number of decimal places of its minor unit
 * (EGP/SAR 2, KWD/BHD/OMR 3, JPY 0).
 */
final readonly class Currency
{
    public function __construct(public string $code, public int $decimals)
    {
        if (! preg_match('/^[A-Z]{3}$/', $code)) {
            throw new InvalidArgumentException("Invalid currency code: {$code}");
        }

        if ($decimals < 0 || $decimals > 4) {
            throw new InvalidArgumentException("Unsupported decimals for {$code}: {$decimals}");
        }
    }

    /** Minor units per major unit as a decimal string, e.g. "1000" for KWD. */
    public function factor(): string
    {
        return '1'.str_repeat('0', $this->decimals);
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }
}
