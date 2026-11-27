<?php

namespace Modules\Accounting\Application\Reporting;

final readonly class TrialBalanceRow
{
    public function __construct(
        public int $accountId,
        public string $code,
        public string $name,
        public string $nameAr,
        public string $type,
        public int $openingNet,
        public int $debit,
        public int $credit,
    ) {}

    public function closingNet(): int
    {
        return $this->openingNet + $this->debit - $this->credit;
    }

    public function openingDebit(): int
    {
        return max(0, $this->openingNet);
    }

    public function openingCredit(): int
    {
        return max(0, -$this->openingNet);
    }

    public function closingDebit(): int
    {
        return max(0, $this->closingNet());
    }

    public function closingCredit(): int
    {
        return max(0, -$this->closingNet());
    }

    public function localizedName(): string
    {
        return app()->getLocale() === 'ar' && $this->nameAr !== '' ? $this->nameAr : $this->name;
    }
}
