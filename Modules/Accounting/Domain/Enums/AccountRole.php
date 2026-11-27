<?php

namespace Modules\Accounting\Domain\Enums;

/**
 * The accounting meaning of a ledger account. Operational modules never hard-code account codes:
 * they ask the company's account mapping for the account that plays a role. Country packs map
 * their own chart of accounts onto these roles.
 */
enum AccountRole: string
{
    case AccountsReceivable = 'accounts_receivable';
    case AccountsPayable = 'accounts_payable';
    case NotesReceivable = 'notes_receivable';
    case NotesPayable = 'notes_payable';
    case Inventory = 'inventory';
    case FixedAssetsCost = 'fixed_assets_cost';
    case AccumulatedDepreciation = 'accumulated_depreciation';
    case Cash = 'cash';
    case Bank = 'bank';
    case VatInput = 'vat_input';
    case VatOutput = 'vat_output';
    case WithholdingTaxPayable = 'withholding_tax_payable';
    case WithholdingTaxReceivable = 'withholding_tax_receivable';
    case RetainedEarnings = 'retained_earnings';
    case OpeningBalanceEquity = 'opening_balance_equity';
    case RealizedFxGainLoss = 'realized_fx_gain_loss';
    case UnrealizedFxGainLoss = 'unrealized_fx_gain_loss';
    case RoundingDifference = 'rounding_difference';
    case SalesRevenue = 'sales_revenue';
    case CostOfGoodsSold = 'cost_of_goods_sold';
    case DepreciationExpense = 'depreciation_expense';
    case BadDebtExpense = 'bad_debt_expense';
    case BankCharges = 'bank_charges';

    /**
     * Control accounts are owned by a sub-ledger: manual journals may not post to them,
     * only the owning module (invoices, payments, stock, assets) can.
     */
    public function isControl(): bool
    {
        return match ($this) {
            self::AccountsReceivable, self::AccountsPayable, self::NotesReceivable, self::NotesPayable,
            self::Inventory, self::FixedAssetsCost, self::AccumulatedDepreciation => true,
            default => false,
        };
    }

    /** The account type a mapped account must have. */
    public function expectedType(): AccountType
    {
        return match ($this) {
            self::AccountsReceivable, self::NotesReceivable, self::Inventory, self::FixedAssetsCost,
            self::AccumulatedDepreciation, self::Cash, self::Bank, self::VatInput, self::WithholdingTaxReceivable => AccountType::Asset,
            self::AccountsPayable, self::NotesPayable, self::VatOutput, self::WithholdingTaxPayable => AccountType::Liability,
            self::RetainedEarnings, self::OpeningBalanceEquity => AccountType::Equity,
            self::SalesRevenue => AccountType::Revenue,
            self::RealizedFxGainLoss, self::UnrealizedFxGainLoss, self::RoundingDifference, self::CostOfGoodsSold,
            self::DepreciationExpense, self::BadDebtExpense, self::BankCharges => AccountType::Expense,
        };
    }

    public function label(): string
    {
        return __('accounting.roles.'.$this->value);
    }
}
