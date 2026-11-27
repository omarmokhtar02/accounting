<?php

namespace Modules\Accounting\Application\DTOs;

final readonly class JournalLineData
{
    /**
     * A foreign-currency line gives $currency plus exactly one of $txnDebit/$txnCredit (minor units of that
     * currency); its base debit/credit are computed from $rate, or from the rate table when $rate is null.
     */
    public function __construct(
        public int $accountId,
        public int $debit,
        public int $credit,
        public ?string $description = null,
        public ?string $currency = null,
        public ?int $txnDebit = null,
        public ?int $txnCredit = null,
        public ?string $rate = null,
    ) {}
}
