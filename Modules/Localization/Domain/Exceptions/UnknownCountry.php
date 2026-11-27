<?php

namespace Modules\Localization\Domain\Exceptions;

use InvalidArgumentException;

final class UnknownCountry extends InvalidArgumentException
{
    public static function forCode(string $code): self
    {
        return new self("No country pack is installed for {$code}.");
    }

    public static function duplicate(string $code): self
    {
        return new self("A country pack for {$code} is already registered.");
    }
}
