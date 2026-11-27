<?php

namespace Modules\Accounting\Application\Reporting;

/**
 * One line of an account's ledger, with the running balance after it (debit minus credit).
 */
final readonly class LedgerLine
{
    public function __construct(
        public int $journalEntryId,
        public string $date,
        public ?string $number,
        public string $entryDescription,
        public ?string $lineDescription,
        public int $debit,
        public int $credit,
        public int $runningBalance,
    ) {}
}
