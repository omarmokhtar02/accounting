<?php

namespace Modules\Accounting\Application\Commands;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Application\AccountRoleResolver;
use Modules\Accounting\Application\DTOs\CreateJournalEntryData;
use Modules\Accounting\Application\DTOs\JournalLineData;
use Modules\Accounting\Application\ExchangeRateResolver;
use Modules\Accounting\Domain\Enums\JournalStatus;
use Modules\Accounting\Domain\Exceptions\AccountRoleError;
use Modules\Accounting\Domain\Exceptions\ExchangeRateError;
use Modules\Accounting\Domain\Exceptions\InvalidJournalState;
use Modules\Accounting\Domain\Models\Account;
use Modules\Accounting\Domain\Models\JournalEntry;
use Modules\Accounting\Domain\Services\JournalBalanceChecker;
use Modules\Shared\Application\RecordAudit;
use Modules\Shared\Domain\Money;
use Modules\Tenancy\Domain\Models\Company;
use Modules\Tenancy\Infrastructure\TenantContext;

final class CreateJournalEntry
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly JournalBalanceChecker $balance,
        private readonly RecordAudit $audit,
        private readonly AccountRoleResolver $roles,
        private readonly ExchangeRateResolver $rates,
    ) {}

    public function handle(CreateJournalEntryData $data): JournalEntry
    {
        $company = $this->context->company();

        if ($data->idempotencyKey !== null) {
            $existing = JournalEntry::query()
                ->where('company_id', $company->id)
                ->where('idempotency_key', $data->idempotencyKey)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $lines = array_map(fn (JournalLineData $line) => $this->toBase($line, $data, $company), $data->lines);

        $this->balance->assertBalanced(array_map(
            fn (array $line) => ['debit' => $line['debit'], 'credit' => $line['credit']],
            $lines,
        ));

        $accountIds = array_values(array_unique(array_map(fn ($line) => $line->accountId, $data->lines)));
        $validAccounts = Account::query()
            ->where('company_id', $company->id)
            ->whereIn('id', $accountIds)
            ->where('status', 'active')
            ->where('is_postable', true)
            ->count();

        if ($validAccounts !== count($accountIds)) {
            throw InvalidJournalState::invalidAccount();
        }

        $this->assertNoManualControlPosting($data, $accountIds);

        try {
            return $this->persist($data, $company, $lines);
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent request with the same idempotency key won the race: return its entry.
            $existing = $data->idempotencyKey === null ? null : JournalEntry::query()
                ->where('company_id', $company->id)
                ->where('idempotency_key', $data->idempotencyKey)
                ->first();

            return $existing ?? throw $e;
        }
    }

    /**
     * Control accounts (receivables, payables, stock, assets) belong to their sub-ledger: a manual
     * journal, which has no source document, may not touch them.
     *
     * @param  list<int>  $accountIds
     */
    private function assertNoManualControlPosting(CreateJournalEntryData $data, array $accountIds): void
    {
        if ($data->sourceType !== null) {
            return;
        }

        $blocked = array_intersect($accountIds, array_values($this->roles->controlAccountIds()));

        if ($blocked !== []) {
            throw AccountRoleError::controlAccountManualPosting((string) Account::query()->whereKey(reset($blocked))->value('code'));
        }
    }

    /**
     * Normalises a line to the base-currency columns, plus the transacted amount and rate for a foreign line.
     *
     * @return array<string, mixed>
     */
    private function toBase(JournalLineData $line, CreateJournalEntryData $data, Company $company): array
    {
        $base = ['account_id' => $line->accountId, 'debit' => $line->debit, 'credit' => $line->credit, 'description' => $line->description];

        if ($line->currency === null || $line->currency === $company->base_currency) {
            return $base;
        }

        $txnDebit = (int) $line->txnDebit;
        $txnCredit = (int) $line->txnCredit;

        if (($txnDebit > 0) === ($txnCredit > 0) || $txnDebit < 0 || $txnCredit < 0) {
            throw ExchangeRateError::invalidForeignLine();
        }

        $rate = $line->rate === null
            ? $this->rates->resolve($line->currency, $data->entryDate)
            : $this->rates->make($line->currency, $company->base_currency, $line->rate);

        $converted = (new Money($txnDebit ?: $txnCredit, $rate->from))->convert($rate)->minor;

        return [
            'account_id' => $line->accountId,
            'debit' => $txnDebit > 0 ? $converted : 0,
            'credit' => $txnCredit > 0 ? $converted : 0,
            'description' => $line->description,
            'txn_currency' => $line->currency,
            'txn_debit' => $txnDebit > 0 ? $txnDebit : null,
            'txn_credit' => $txnCredit > 0 ? $txnCredit : null,
            'exchange_rate' => $rate->rate,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function persist(CreateJournalEntryData $data, Company $company, array $lines): JournalEntry
    {
        return DB::transaction(function () use ($data, $company, $lines): JournalEntry {
            $entry = JournalEntry::create([
                'company_id' => $company->id,
                'entry_date' => $data->entryDate,
                'description' => $data->description,
                'status' => JournalStatus::Draft,
                'currency' => $company->base_currency,
                'idempotency_key' => $data->idempotencyKey,
                'source_type' => $data->sourceType,
                'source_id' => $data->sourceId,
                'created_by' => auth()->id(),
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create(['company_id' => $company->id] + $line);
            }

            $this->audit->handle('journal_entry', $entry->id, 'created', null, ['status' => 'draft']);

            return $entry;
        });
    }
}
