<?php

namespace Modules\Accounting\Domain\Exceptions;

final class ChartAlreadyExists extends DomainException
{
    public static function forCompany(): self
    {
        return new self('accounting.errors.chart_already_exists');
    }
}
