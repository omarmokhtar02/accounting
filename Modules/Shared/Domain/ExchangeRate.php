<?php

namespace Modules\Shared\Domain;

use Modules\Shared\Domain\Exceptions\InvalidMoneyAmount;

/**
 * Units of the quote currency ($to) per one unit of the base currency ($from).
 * The rate is kept as a decimal string (up to 10 decimals) and applied with bcmath.
 */
final readonly class ExchangeRate
{
    public function __construct(
        public Currency $from,
        public Currency $to,
        public string $rate,
    ) {
        if (! preg_match('/^\d+(\.\d{1,10})?$/', $rate) || bccomp($rate, '0', 10) <= 0) {
            throw InvalidMoneyAmount::invalidRate($rate);
        }
    }

    public static function identity(Currency $currency): self
    {
        return new self($currency, $currency, '1');
    }

    /** Drops insignificant trailing zeros of a decimal string: "50.5000" => "50.5", "50.00" => "50", "50" stays "50". */
    public static function plain(string $rate): string
    {
        return str_contains($rate, '.') ? rtrim(rtrim($rate, '0'), '.') : $rate;
    }

    public function inverse(): self
    {
        return new self($this->to, $this->from, rtrim(rtrim(bcdiv('1', $this->rate, 10), '0'), '.') ?: '0.0000000001');
    }
}
