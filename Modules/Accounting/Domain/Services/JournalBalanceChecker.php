<?php

namespace Modules\Accounting\Domain\Services;

use Modules\Accounting\Domain\Exceptions\JournalNotBalanced;

/**
 * Enforces the core double-entry invariant on integer minor-unit amounts.
 */
final class JournalBalanceChecker
{
    /**
     * @param  list<array{debit: int, credit: int}>  $lines
     */
    public function assertBalanced(array $lines): void
    {
        if (count($lines) < 2) {
            throw JournalNotBalanced::tooFewLines();
        }

        $debits = 0;
        $credits = 0;

        foreach ($lines as $line) {
            $debits += $line['debit'];
            $credits += $line['credit'];
        }

        if ($debits !== $credits || $debits === 0) {
            throw JournalNotBalanced::totals($debits, $credits);
        }
    }
}
