<?php

namespace Modules\Accounting\Application\Reporting;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Domain\Models\Account;
use Modules\Accounting\Domain\Models\JournalLine;
use Modules\Tenancy\Infrastructure\TenantContext;

/**
 * Trial balance read model. The database aggregates (never PHP loops over journal lines), only POSTED
 * entries count (drafts are not in the books; reversed originals still count because their reversal
 * is posted too and the two net to zero), and every query goes through the tenant-scoped model.
 */
final class TrialBalanceReport
{
    public function __construct(private readonly TenantContext $context) {}

    public function build(CarbonImmutable $from, CarbonImmutable $to, bool $hideZeroRows = true): TrialBalanceResult
    {
        $company = $this->context->company();

        $opening = $this->sums($company->id, fn ($q) => $q->whereDate('journal_entries.entry_date', '<', $from->toDateString()));
        $period = $this->sums($company->id, fn ($q) => $q->whereDate('journal_entries.entry_date', '>=', $from->toDateString())->whereDate('journal_entries.entry_date', '<=', $to->toDateString()));

        $rows = [];

        foreach (Account::query()->where('company_id', $company->id)->where('is_postable', true)->orderBy('code')->get() as $account) {
            $open = $opening[$account->id] ?? ['debit' => 0, 'credit' => 0];
            $move = $period[$account->id] ?? ['debit' => 0, 'credit' => 0];

            $row = new TrialBalanceRow(
                $account->id, $account->code, $account->name, (string) $account->name_ar, $account->type->value,
                $open['debit'] - $open['credit'], $move['debit'], $move['credit'],
            );

            if ($hideZeroRows && $row->openingNet === 0 && $row->debit === 0 && $row->credit === 0) {
                continue;
            }

            $rows[] = $row;
        }

        return new TrialBalanceResult($company->base_currency, $from, $to, $rows);
    }

    /**
     * @param  callable(Builder): mixed  $dateFilter
     * @return array<int, array{debit: int, credit: int}> account id => totals
     */
    private function sums(int $companyId, callable $dateFilter): array
    {
        $query = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.company_id', $companyId)
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', '!=', 'draft');

        $dateFilter($query);

        return $query
            ->groupBy('journal_lines.account_id')
            ->select('journal_lines.account_id', DB::raw('SUM(journal_lines.debit) as debit'), DB::raw('SUM(journal_lines.credit) as credit'))
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->account_id => ['debit' => (int) $r->debit, 'credit' => (int) $r->credit]])
            ->all();
    }
}
