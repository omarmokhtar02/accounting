<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Modules\Shared\Domain\Contracts\CurrencyRegistry;
use Modules\Shared\Domain\Money as MoneyValue;

/**
 * <x-money :minor="$line->debit" :currency="$entry->currency" /> renders an integer minor-unit
 * amount with the currency's own decimals (KWD 3, EGP 2, JPY 0).
 */
class Money extends Component
{
    public function __construct(public int $minor, public string $currency, public bool $blankZero = false) {}

    public function render(): string
    {
        if ($this->blankZero && $this->minor === 0) {
            return '';
        }

        $money = new MoneyValue($this->minor, app(CurrencyRegistry::class)->get($this->currency));

        return e($money->format());
    }
}
