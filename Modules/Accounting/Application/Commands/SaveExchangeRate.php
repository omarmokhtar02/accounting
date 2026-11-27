<?php

namespace Modules\Accounting\Application\Commands;

use Carbon\CarbonImmutable;
use Modules\Accounting\Domain\Exceptions\ExchangeRateError;
use Modules\Accounting\Domain\Models\ExchangeRateEntry;
use Modules\Shared\Application\RecordAudit;
use Modules\Shared\Domain\Contracts\CurrencyRegistry;
use Modules\Shared\Domain\ExchangeRate;
use Modules\Tenancy\Infrastructure\TenantContext;

final class SaveExchangeRate
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly CurrencyRegistry $currencies,
        private readonly RecordAudit $audit,
    ) {}

    /**
     * Records (or replaces) the rate of one foreign currency for a day. Posted journals keep the
     * rate they were posted with, so editing a rate only affects entries created afterwards.
     */
    public function handle(string $currencyCode, CarbonImmutable $date, string $rate, string $source = 'manual'): ExchangeRateEntry
    {
        $company = $this->context->company();
        $currencyCode = strtoupper($currencyCode);

        if ($currencyCode === $company->base_currency) {
            throw ExchangeRateError::baseCurrency($currencyCode);
        }

        // Validates the format (positive decimal, at most 10 decimals) in one place.
        new ExchangeRate($this->currencies->get($currencyCode), $this->currencies->get($company->base_currency), $rate);

        $entry = ExchangeRateEntry::query()->updateOrCreate(
            ['company_id' => $company->id, 'currency_code' => $currencyCode, 'rate_date' => $date->toDateString()],
            ['rate' => $rate, 'source' => $source, 'created_by' => auth()->id()],
        );

        $this->audit->handle('exchange_rate', $entry->id, 'saved', null, ['currency' => $currencyCode, 'date' => $date->toDateString(), 'rate' => $rate]);

        return $entry;
    }
}
