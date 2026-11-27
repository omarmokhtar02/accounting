<?php

namespace Modules\Accounting\Application\Reporting;

use Carbon\CarbonImmutable;

/**
 * A trial balance for one company over a date range. All amounts are integer minor units of the
 * company currency. "Net" figures are debit minus credit: positive = debit balance.
 */
final readonly class TrialBalanceResult
{
    /**
     * @param  list<TrialBalanceRow>  $rows
     */
    public function __construct(
        public string $currency,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public array $rows,
    ) {}

    public function totalOpeningNet(): int
    {
        return array_sum(array_map(fn (TrialBalanceRow $r) => $r->openingNet, $this->rows));
    }

    public function totalDebit(): int
    {
        return array_sum(array_map(fn (TrialBalanceRow $r) => $r->debit, $this->rows));
    }

    public function totalCredit(): int
    {
        return array_sum(array_map(fn (TrialBalanceRow $r) => $r->credit, $this->rows));
    }

    public function totalClosingNet(): int
    {
        return array_sum(array_map(fn (TrialBalanceRow $r) => $r->closingNet(), $this->rows));
    }

    /** Closing balances of accounts in debit (sum of positive nets). */
    public function totalClosingDebit(): int
    {
        return array_sum(array_map(fn (TrialBalanceRow $r) => $r->closingDebit(), $this->rows));
    }

    public function totalClosingCredit(): int
    {
        return array_sum(array_map(fn (TrialBalanceRow $r) => $r->closingCredit(), $this->rows));
    }

    /** The defining property of a trial balance: total debits equal total credits, and so do closing balances. */
    public function isBalanced(): bool
    {
        return $this->totalDebit() === $this->totalCredit()
            && $this->totalOpeningNet() === 0
            && $this->totalClosingNet() === 0
            && $this->totalClosingDebit() === $this->totalClosingCredit();
    }
}
