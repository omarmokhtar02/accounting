<?php

namespace Modules\Accounting\Application\Import;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Accounting\Application\Commands\SaveExchangeRate;
use Modules\Shared\Domain\Contracts\CurrencyRegistry;
use Modules\Shared\Domain\Exceptions\InvalidMoneyAmount;
use Modules\Shared\Domain\Exceptions\UnknownCurrency;
use Modules\Shared\Domain\ExchangeRate;
use Modules\Tenancy\Infrastructure\TenantContext;

/**
 * Daily rates from a sheet: currency, date (Y-m-d), rate (base-currency units per one foreign unit).
 * A rate for a currency and day that already exists is replaced.
 */
final class ExchangeRatesImporter implements Importer
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly CurrencyRegistry $currencies,
        private readonly SaveExchangeRate $save,
    ) {}

    public function key(): string
    {
        return 'rates';
    }

    public function requiredColumns(): array
    {
        return ['currency', 'date', 'rate'];
    }

    public function templateColumns(): array
    {
        return ['currency', 'date', 'rate'];
    }

    public function sampleRows(): array
    {
        return [['USD', '2026-01-01', '48.5'], ['SAR', '2026-01-01', '12.9']];
    }

    public function run(array $rows, string $fileHash, bool $commit): ImportReport
    {
        $base = $this->currencies->get($this->context->company()->base_currency);
        $errors = [];
        $valid = [];

        foreach ($rows as $number => $row) {
            $code = strtoupper($row['currency'] ?? '');
            $key = $code.'|'.($row['date'] ?? '');

            try {
                if ($code === $base->code) {
                    $errors[] = ['row' => $number, 'key' => 'accounting.errors.rate_base_currency', 'replace' => ['currency' => $code]];

                    continue;
                }

                new ExchangeRate($this->currencies->get($code), $base, $row['rate'] ?? '');
                $date = ImportDate::parse($row['date'] ?? '');

                if ($date === null) {
                    throw new InvalidArgumentException('date');
                }
            } catch (UnknownCurrency) {
                $errors[] = ['row' => $number, 'key' => 'accounting.import.unknown_currency', 'replace' => ['value' => $code]];

                continue;
            } catch (InvalidMoneyAmount) {
                $errors[] = ['row' => $number, 'key' => 'accounting.import.bad_rate', 'replace' => ['value' => $row['rate'] ?? '']];

                continue;
            } catch (InvalidArgumentException) {
                $errors[] = ['row' => $number, 'key' => 'accounting.import.bad_date', 'replace' => ['value' => $row['date'] ?? '']];

                continue;
            }

            if (isset($valid[$key])) {
                $errors[] = ['row' => $number, 'key' => 'accounting.import.duplicate_rate', 'replace' => ['currency' => $code, 'date' => $row['date']]];

                continue;
            }

            $valid[$key] = [$code, $date, $row['rate']];
        }

        if ($errors !== [] || ! $commit) {
            return new ImportReport(count($rows), 0, 0, $errors, false);
        }

        DB::transaction(function () use ($valid): void {
            foreach ($valid as [$code, $date, $rate]) {
                $this->save->handle($code, $date, $rate, 'import');
            }
        });

        return new ImportReport(count($rows), count($valid), 0, [], true);
    }
}
