<?php

namespace Modules\Accounting\Domain\Exceptions;

final class AccountingPeriodClosed extends DomainException
{
    public static function forDate(string $date): self
    {
        return new self('accounting.errors.period_closed', ['date' => $date]);
    }

    public static function notFound(string $date): self
    {
        return new self('accounting.errors.period_not_found', ['date' => $date]);
    }
}
