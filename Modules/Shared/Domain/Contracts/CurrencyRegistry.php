<?php

namespace Modules\Shared\Domain\Contracts;

use Modules\Shared\Domain\Currency;
use Modules\Shared\Domain\Exceptions\UnknownCurrency;

interface CurrencyRegistry
{
    /** @throws UnknownCurrency */
    public function get(string $code): Currency;

    public function exists(string $code): bool;

    /** @return array<string, Currency> code => currency */
    public function all(): array;
}
