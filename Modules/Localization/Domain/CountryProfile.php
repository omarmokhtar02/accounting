<?php

namespace Modules\Localization\Domain;

use InvalidArgumentException;
use Modules\Accounting\Domain\Enums\AccountRole;

/**
 * The identity and rules of a country that the core needs to know, independent of its chart of accounts.
 */
final readonly class CountryProfile
{
    /**
     * @param  list<string>  $weekend  ISO day names, e.g. ['friday', 'saturday']
     */
    public function __construct(
        public string $code,
        public string $nameEn,
        public string $nameAr,
        public string $currency,
        public string $locale,
        public int $fiscalYearStartMonth,
        public string $accountingStandard,
        public bool $vatEnabled,
        public bool $withholdingTax,
        public bool $hijriDisplay = false,
        public array $weekend = ['friday', 'saturday'],
    ) {
        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            throw new InvalidArgumentException("Invalid country code: {$code}");
        }

        if ($fiscalYearStartMonth < 1 || $fiscalYearStartMonth > 12) {
            throw new InvalidArgumentException('Fiscal year start month must be 1-12.');
        }
    }

    /**
     * Roles the core needs mapped before a company can use sub-ledgers. VAT and withholding roles are
     * only required for countries that levy those taxes (Kuwait, for example, has no VAT).
     *
     * @return list<AccountRole>
     */
    public function requiredRoles(): array
    {
        $always = [
            AccountRole::AccountsReceivable, AccountRole::AccountsPayable, AccountRole::NotesReceivable,
            AccountRole::NotesPayable, AccountRole::Inventory, AccountRole::FixedAssetsCost,
            AccountRole::AccumulatedDepreciation, AccountRole::Cash, AccountRole::Bank,
            AccountRole::RetainedEarnings, AccountRole::OpeningBalanceEquity,
            AccountRole::RealizedFxGainLoss, AccountRole::UnrealizedFxGainLoss, AccountRole::RoundingDifference,
            AccountRole::SalesRevenue, AccountRole::CostOfGoodsSold, AccountRole::DepreciationExpense,
            AccountRole::BadDebtExpense, AccountRole::BankCharges,
        ];

        if ($this->vatEnabled) {
            $always[] = AccountRole::VatInput;
            $always[] = AccountRole::VatOutput;
        }

        if ($this->withholdingTax) {
            $always[] = AccountRole::WithholdingTaxPayable;
            $always[] = AccountRole::WithholdingTaxReceivable;
        }

        return $always;
    }
}
