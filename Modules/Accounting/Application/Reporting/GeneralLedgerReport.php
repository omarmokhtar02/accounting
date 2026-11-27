<?php

namespace Modules\Accounting\Application\Reporting;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Domain\Models\Account;
use Modules\Accounting\Domain\Models\JournalLine;
use Modules\Tenancy\Infrastructure\TenantContext;

/**
 * An account's ledger (also the account statement): opening balance, every posted line in date order with a
 * running balance, and the closing balance. Reads go through the tenant-scoped model, and the opening
 * balance is aggregated in the database rather than summed in PHP.
 */
final class GeneralLedgerReport
{
    /** UI pages stop here and ask the user to narrow the dates; exports pass null to get everything. */
    public const DEFAULT_LIMIT = 2000;

    public function __construct(private readonly TenantContext $context) {}

    public function build(Account $account, CarbonImmutable $from, CarbonImmutable $to, ?int $limit = self::DEFAULT_LIMIT): GeneralLedgerResult
    {
        $company = $this->context->company();

        abort_unless($account->company_id === $company->id, 404);

        $opening = $this->base($company->id, $account->id)
            ->whereDate('journal_entries.entry_date', '<', $from->toDateString())
            ->select(DB::raw('COALESCE(SUM(journal_lines.debit), 0) as debit'), DB::raw('COALESCE(SUM(journal_lines.credit), 0) as credit'))
            ->first();

        $openingBalance = (int) $opening->debit - (int) $opening->credit;

        $query = $this->base($company->id, $account->id)
            ->whereDate('journal_entries.entry_date', '>=', $from->toDateString())
            ->whereDate('journal_entries.entry_date', '<=', $to->toDateString())
            ->orderBy('journal_entries.entry_date')->orderBy('journal_entries.id')->orderBy('journal_lines.id')
            ->select('journal_entries.id as entry_id', 'journal_entries.entry_date', 'journal_entries.number', 'journal_entries.description as entry_description',
                'journal_lines.description as line_description', 'journal_lines.debit', 'journal_lines.credit');

        $truncated = false;

        if ($limit !== null) {
            $query->limit($limit + 1);
        }

        $rows = $query->get();

        if ($limit !== null && $rows->count() > $limit) {
            $rows = $rows->take($limit);
            $truncated = true;
        }

        $running = $openingBalance;
        $lines = [];

        foreach ($rows as $row) {
            $running += (int) $row->debit - (int) $row->credit;
            $lines[] = new LedgerLine(
                (int) $row->entry_id, substr((string) $row->entry_date, 0, 10), $row->number, (string) $row->entry_description,
                $row->line_description, (int) $row->debit, (int) $row->credit, $running,
            );
        }

        return new GeneralLedgerResult($account, $company->base_currency, $from, $to, $openingBalance, $lines, $truncated);
    }

    private function base(int $companyId, int $accountId): Builder
    {
        return JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.company_id', $companyId)
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_lines.account_id', $accountId)
            ->where('journal_entries.status', '!=', 'draft');
    }
}
