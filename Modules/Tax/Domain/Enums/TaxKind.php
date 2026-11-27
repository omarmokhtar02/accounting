<?php

namespace Modules\Tax\Domain\Enums;

enum TaxKind: string
{
    case Vat = 'vat';
    case Withholding = 'withholding';
    case TableTax = 'table_tax';
    case StampDuty = 'stamp_duty';
    case Other = 'other';

    public function label(): string
    {
        return __('tax.kinds.'.$this->value);
    }
}
