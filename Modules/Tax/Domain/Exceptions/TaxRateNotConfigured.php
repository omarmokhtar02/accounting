<?php

namespace Modules\Tax\Domain\Exceptions;

use RuntimeException;

/**
 * No rate is in force for this country, tax code and date. The platform admin must schedule one.
 */
final class TaxRateNotConfigured extends RuntimeException
{
    /** @param array<string, scalar> $replace */
    public function __construct(public readonly string $translationKey, public readonly array $replace = [])
    {
        parent::__construct($translationKey);
    }

    public function userMessage(): string
    {
        return __($this->translationKey, $this->replace);
    }

    public static function for(string $country, string $code, string $date): self
    {
        return new self('tax.errors.rate_not_configured', ['country' => $country, 'code' => $code, 'date' => $date]);
    }
}
