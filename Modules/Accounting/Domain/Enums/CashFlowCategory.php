<?php

namespace Modules\Accounting\Domain\Enums;

enum CashFlowCategory: string
{
    case None = 'none';
    case Cash = 'cash';
    case Operating = 'operating';
    case Investing = 'investing';
    case Financing = 'financing';

    public function label(): string
    {
        return __('accounting.cash_flow_categories.'.$this->value);
    }
}
