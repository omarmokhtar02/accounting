<?php

namespace Modules\Shared\Infrastructure;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Domain\Contracts\CurrencyRegistry;
use Modules\Shared\Domain\Currency;
use Modules\Shared\Domain\Exceptions\UnknownCurrency;

/**
 * Reads the global `currencies` table once per request/job and memoises the result.
 */
final class DatabaseCurrencyRegistry implements CurrencyRegistry
{
    /** @var array<string, Currency>|null */
    private ?array $cache = null;

    public function get(string $code): Currency
    {
        return $this->all()[strtoupper($code)] ?? throw UnknownCurrency::forCode($code);
    }

    public function exists(string $code): bool
    {
        return isset($this->all()[strtoupper($code)]);
    }

    /** @return array<string, Currency> code => currency */
    public function all(): array
    {
        return $this->cache ??= DB::table('currencies')->get(['code', 'decimals'])
            ->mapWithKeys(fn ($row) => [$row->code => new Currency($row->code, (int) $row->decimals)])
            ->all();
    }
}
