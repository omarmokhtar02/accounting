<?php

namespace Modules\Shared\Domain\Exceptions;

use InvalidArgumentException;

final class UnknownCurrency extends InvalidArgumentException
{
    public static function forCode(string $code): self
    {
        return new self("Unknown currency: {$code}");
    }
}
