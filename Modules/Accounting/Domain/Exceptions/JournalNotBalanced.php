<?php

namespace Modules\Accounting\Domain\Exceptions;

final class JournalNotBalanced extends DomainException
{
    public static function totals(int $debits, int $credits): self
    {
        return new self('accounting.errors.journal_not_balanced', [
            'debits' => number_format($debits / 100, 2),
            'credits' => number_format($credits / 100, 2),
        ]);
    }

    public static function tooFewLines(): self
    {
        return new self('accounting.errors.journal_too_few_lines');
    }
}
