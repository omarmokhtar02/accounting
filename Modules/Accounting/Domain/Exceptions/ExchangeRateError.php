<?php

namespace Modules\Accounting\Domain\Exceptions;

final class ExchangeRateError extends DomainException
{
    public static function missing(string $currency, string $date): self
    {
        return new self('accounting.errors.rate_missing', ['currency' => $currency, 'date' => $date]);
    }

    public static function baseCurrency(string $currency): self
    {
        return new self('accounting.errors.rate_base_currency', ['currency' => $currency]);
    }

    public static function invalidForeignLine(): self
    {
        return new self('accounting.errors.foreign_line_invalid');
    }
}
