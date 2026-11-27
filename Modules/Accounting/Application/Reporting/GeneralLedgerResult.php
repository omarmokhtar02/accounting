<?php

namespace Modules\Accounting\Application\Reporting;

use Carbon\CarbonImmutable;
use Modules\Accounting\Domain\Models\Account;

final readonly class GeneralLedgerResult
{
    /**
     * @param  list<LedgerLine>  $lines
     */
    public function __construct(
        public Account $account,
        public string $currency,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public int $openingBalance,
        public array $lines,
        public bool $truncated = false,
    ) {}

    public function totalDebit(): int
    {
        return array_sum(array_map(fn (LedgerLine $l) => $l->debit, $this->lines));
    }

    public function totalCredit(): int
    {
        return array_sum(array_map(fn (LedgerLine $l) => $l->credit, $this->lines));
    }

    public function closingBalance(): int
    {
        return $this->openingBalance + $this->totalDebit() - $this->totalCredit();
    }
}
