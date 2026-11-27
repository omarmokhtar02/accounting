<?php

namespace Modules\Tax\Domain\Exceptions;

use RuntimeException;

final class InvalidTaxRateChange extends RuntimeException
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

    public static function inThePast(): self
    {
        return new self('tax.errors.in_the_past');
    }

    public static function duplicateDate(string $date): self
    {
        return new self('tax.errors.duplicate_date', ['date' => $date]);
    }

    public static function rateOutOfRange(): self
    {
        return new self('tax.errors.rate_out_of_range');
    }

    public static function alreadyInForce(): self
    {
        return new self('tax.errors.already_in_force');
    }

    public static function unknownCode(string $country, string $code): self
    {
        return new self('tax.errors.unknown_code', ['country' => $country, 'code' => $code]);
    }
}
