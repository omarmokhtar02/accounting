<?php

namespace Modules\Shared\Domain\Exceptions;

use LogicException;

final class CurrencyMismatch extends LogicException
{
    public static function between(string $left, string $right): self
    {
        return new self("Cannot combine {$left} with {$right} without an exchange rate.");
    }
}
