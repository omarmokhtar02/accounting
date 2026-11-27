<?php

namespace Modules\Accounting\Application;

use Carbon\CarbonImmutable;
use Modules\Accounting\Domain\Exceptions\ExchangeRateError;
use Modules\Accounting\Domain\Models\ExchangeRateEntry;
use Modules\Shared\Domain\Contracts\CurrencyRegistry;
use Modules\Shared\Domain\ExchangeRate;
use Modules\Tenancy\Infrastructure\TenantContext;

/**
 * Finds the rate to convert a foreign currency into the company's base currency: the most recent
 * rate on or before the transaction date (weekends and holidays reuse the last published rate).
 */
final class ExchangeRateResolver
{
    public function __construct(private readonly TenantContext $context, private readonly CurrencyRegistry $currencies) {}

    public function resolve(string $currencyCode, CarbonImmutable $date): ExchangeRate
    {
        $base = $this->context->company()->base_currency;

        if ($currencyCode === $base) {
            return ExchangeRate::identity($this->currencies->get($base));
        }

        $entry = ExchangeRateEntry::query()
            ->where('company_id', $this->context->companyId())
            ->where('currency_code', $currencyCode)
            ->whereDate('rate_date', '<=', $date->toDateString())
            ->orderByDesc('rate_date')
            ->first();

        if ($entry === null) {
            throw ExchangeRateError::missing($currencyCode, $date->toDateString());
        }

        return $this->make($currencyCode, $base, ExchangeRate::plain((string) $entry->rate));
    }

    /** Builds a rate from an explicit decimal string (a rate typed on a line). */
    public function make(string $currencyCode, string $baseCode, string $rate): ExchangeRate
    {
        return new ExchangeRate($this->currencies->get($currencyCode), $this->currencies->get($baseCode), $rate);
    }
}
