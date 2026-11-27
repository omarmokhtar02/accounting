<?php

namespace Modules\Accounting\Application\Import;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Application\AccountRoleResolver;
use Modules\Accounting\Application\Commands\CreateJournalEntry;
use Modules\Accounting\Application\DTOs\CreateJournalEntryData;
use Modules\Accounting\Application\DTOs\JournalLineData;
use Modules\Accounting\Domain\Exceptions\DomainException;
use Modules\Accounting\Domain\Models\Account;
use Modules\Accounting\Domain\Models\JournalEntry;
use Modules\Accounting\Domain\Services\JournalBalanceChecker;
use Modules\Shared\Domain\Contracts\CurrencyRegistry;
use Modules\Shared\Domain\Exceptions\InvalidMoneyAmount;
use Modules\Shared\Domain\Money;
use Modules\Tenancy\Infrastructure\TenantContext;

/**
 * Historical journals and opening balances: ref, date, account_code, debit, credit, description. Lines
 * sharing a ref form one entry, which must balance. Entries arrive as DRAFTS so a person reviews and
 * posts them. The ref plus the file's hash is the idempotency key: uploading the same file twice
 * creates nothing new. An opening balance is simply one ref dated the day before the books start.
 */
final class JournalsImporter implements Importer
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly CreateJournalEntry $create,
        private readonly JournalBalanceChecker $balance,
        private readonly AccountRoleResolver $roles,
        private readonly CurrencyRegistry $currencies,
    ) {}

    public function key(): string
    {
        return 'journals';
    }

    public function requiredColumns(): array
    {
        return ['ref', 'date', 'account_code'];
    }

    public function templateColumns(): array
    {
        return ['ref', 'date', 'account_code', 'debit', 'credit', 'description'];
    }

    public function sampleRows(): array
    {
        return [
            ['OB-1', '2025-12-31', '1010', '50000.00', '', 'Opening balance'],
            ['OB-1', '2025-12-31', '3010', '', '50000.00', 'Opening balance'],
        ];
    }

    public function run(array $rows, string $fileHash, bool $commit): ImportReport
    {
        $company = $this->context->company();
        $currency = $this->currencies->get($company->base_currency);
        $accounts = Account::query()->where('company_id', $company->id)->where('status', 'active')->where('is_postable', true)->get()->keyBy('code');
        $control = array_values($this->roles->controlAccountIds());
        $errors = [];
        $groups = [];

        foreach ($rows as $number => $row) {
            $ref = $row['ref'] ?? '';
            $account = $accounts[$row['account_code'] ?? ''] ?? null;
            $debit = 0;
            $credit = 0;

            try {
                $debit = ($row['debit'] ?? '') === '' ? 0 : Money::fromDecimal($row['debit'], $currency)->minor;
                $credit = ($row['credit'] ?? '') === '' ? 0 : Money::fromDecimal($row['credit'], $currency)->minor;
            } catch (InvalidMoneyAmount) {
                $errors[] = ['row' => $number, 'key' => 'accounting.import.bad_amount', 'replace' => []];

                continue;
            }

            $date = ImportDate::parse($row['date'] ?? '');

            $problem = match (true) {
                $ref === '' => 'accounting.import.bad_ref',
                $date === null => 'accounting.import.bad_date',
                $account === null => 'accounting.import.unknown_account',
                $debit < 0 || $credit < 0 || ($debit > 0) === ($credit > 0) => 'accounting.import.one_side',
                in_array($account->id, $control, true) => 'accounting.import.control_account',
                isset($groups[$ref]) && $groups[$ref]['date'] !== $row['date'] => 'accounting.import.mixed_dates',
                default => null,
            };

            if ($problem !== null) {
                $errors[] = ['row' => $number, 'key' => $problem, 'replace' => ['value' => $row['account_code'] ?? '', 'ref' => $ref]];

                continue;
            }

            $groups[$ref]['date'] = $row['date'];
            $groups[$ref]['first_row'] ??= $number;
            $groups[$ref]['lines'][] = new JournalLineData($account->id, $debit, $credit, ($row['description'] ?? '') ?: null);
        }

        foreach ($groups as $ref => $group) {
            try {
                $this->balance->assertBalanced(array_map(fn (JournalLineData $l) => ['debit' => $l->debit, 'credit' => $l->credit], $group['lines']));
            } catch (DomainException) {
                $errors[] = ['row' => $group['first_row'], 'key' => 'accounting.import.unbalanced', 'replace' => ['ref' => $ref]];
            }
        }

        usort($errors, fn (array $a, array $b) => $a['row'] <=> $b['row']);

        if ($errors !== [] || ! $commit) {
            return new ImportReport(count($rows), 0, 0, $errors, false);
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($groups, $fileHash, &$created, &$skipped): void {
            foreach ($groups as $ref => $group) {
                $key = 'import:'.substr($fileHash, 0, 16).':'.$ref;
                $existed = JournalEntry::query()->where('company_id', $this->context->companyId())->where('idempotency_key', $key)->exists();

                $this->create->handle(new CreateJournalEntryData(
                    ImportDate::parse($group['date']), 'Import '.$ref, $group['lines'], $key,
                ));

                $existed ? $skipped++ : $created++;
            }
        });

        return new ImportReport(count($rows), $created, $skipped, [], true);
    }
}
