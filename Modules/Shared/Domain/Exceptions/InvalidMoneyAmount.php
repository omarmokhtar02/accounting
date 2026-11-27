<?php

namespace Modules\Shared\Domain\Exceptions;

use InvalidArgumentException;

final class InvalidMoneyAmount extends InvalidArgumentException
{
    public static function notANumber(string $input): self
    {
        return new self("Invalid amount: {$input}");
    }

    public static function tooManyDecimals(string $input, string $currency, int $allowed): self
    {
        return new self("Amount {$input} has more than {$allowed} decimals for {$currency}.");
    }

    public static function invalidRate(string $input): self
    {
        return new self("Invalid exchange rate: {$input}");
    }

    public static function emptyAllocation(): self
    {
        return new self('Cannot allocate across zero ratios.');
    }
}
