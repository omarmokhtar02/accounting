<?php

namespace Modules\Egypt\Domain;

use Modules\Accounting\Domain\Enums\AccountRole as R;
use Modules\Accounting\Domain\Enums\AccountType as T;
use Modules\Accounting\Domain\Enums\CashFlowCategory as CF;
use Modules\Accounting\Domain\ValueObjects\AccountSeed;
use Modules\Localization\Domain\ChartBuilder;

/**
 * Chart of accounts for an Egyptian company, organised by the structure of the financial statements
 * under the Egyptian Accounting Standards (EAS). It is a template to start from, not an official
 * government chart: a licensed Egyptian accountant should review it before first use.
 */
final class EgyptChartOfAccounts
{
    /** @return list<AccountSeed> */
    public static function seeds(): array
    {
        return (new ChartBuilder)
            // 1 — Assets
            ->header('1', 'Assets', 'الأصول', T::Asset)
            ->header('11', 'Fixed assets', 'الأصول الثابتة', T::Asset, '1')
            ->leaf('1101', 'Fixed assets at cost', 'الأصول الثابتة بالتكلفة', T::Asset, '11', R::FixedAssetsCost, CF::Investing)
            ->leaf('1102', 'Land', 'الأراضي', T::Asset, '11', null, CF::Investing)
            ->leaf('1103', 'Buildings', 'المباني', T::Asset, '11', null, CF::Investing)
            ->leaf('1104', 'Machinery and equipment', 'الآلات والمعدات', T::Asset, '11', null, CF::Investing)
            ->leaf('1105', 'Vehicles', 'وسائل النقل والانتقال', T::Asset, '11', null, CF::Investing)
            ->leaf('1106', 'Furniture and office equipment', 'الأثاث والتجهيزات المكتبية', T::Asset, '11', null, CF::Investing)
            ->leaf('1107', 'Computers and software', 'أجهزة الحاسب والبرمجيات', T::Asset, '11', null, CF::Investing)
            ->leaf('1108', 'Projects under construction', 'مشروعات تحت التنفيذ', T::Asset, '11', null, CF::Investing)
            ->leaf('1109', 'Accumulated depreciation', 'مجمع الإهلاك', T::Asset, '11', R::AccumulatedDepreciation)
            ->leaf('1110', 'Intangible assets', 'الأصول غير الملموسة', T::Asset, '11', null, CF::Investing)
            ->header('12', 'Current assets', 'الأصول المتداولة', T::Asset, '1')
            ->leaf('1201', 'Inventory', 'المخزون', T::Asset, '12', R::Inventory, CF::Operating)
            ->leaf('1202', 'Goods in transit', 'بضاعة بالطريق', T::Asset, '12', null, CF::Operating)
            ->leaf('1203', 'Customers', 'العملاء', T::Asset, '12', R::AccountsReceivable, CF::Operating)
            ->leaf('1204', 'Allowance for doubtful debts', 'مخصص ديون مشكوك في تحصيلها', T::Asset, '12', null, CF::Operating)
            ->leaf('1205', 'Notes receivable', 'أوراق القبض', T::Asset, '12', R::NotesReceivable, CF::Operating)
            ->leaf('1206', 'Cheques under collection', 'شيكات تحت التحصيل', T::Asset, '12', null, CF::Operating)
            ->leaf('1207', 'Employee advances and loans', 'سلف وقروض العاملين', T::Asset, '12', null, CF::Operating)
            ->leaf('1208', 'Prepaid expenses', 'مصروفات مدفوعة مقدماً', T::Asset, '12', null, CF::Operating)
            ->leaf('1209', 'Other debit balances', 'أرصدة مدينة أخرى', T::Asset, '12', null, CF::Operating)
            ->leaf('1210', 'VAT input', 'ضريبة القيمة المضافة - مدخلات', T::Asset, '12', R::VatInput, CF::Operating)
            ->leaf('1211', 'Withholding tax receivable (deducted by customers)', 'ضريبة الخصم والإضافة - مدينة', T::Asset, '12', R::WithholdingTaxReceivable, CF::Operating)
            ->leaf('1212', 'Income tax prepayments', 'ضرائب الدخل المدفوعة مقدماً', T::Asset, '12', null, CF::Operating)
            ->leaf('1213', 'Cash on hand', 'الخزينة', T::Asset, '12', R::Cash, CF::Cash)
            ->leaf('1214', 'Banks - current accounts', 'البنوك - حسابات جارية', T::Asset, '12', R::Bank, CF::Cash)
            ->leaf('1215', 'Petty cash (imprest)', 'عهدة نقدية (سلفة مستديمة)', T::Asset, '12', null, CF::Cash)
            // 2 — Liabilities
            ->header('2', 'Liabilities', 'الخصوم', T::Liability)
            ->header('21', 'Current liabilities', 'الخصوم المتداولة', T::Liability, '2')
            ->leaf('2101', 'Suppliers', 'الموردون', T::Liability, '21', R::AccountsPayable, CF::Operating)
            ->leaf('2102', 'Notes payable', 'أوراق الدفع', T::Liability, '21', R::NotesPayable, CF::Operating)
            ->leaf('2103', 'Accrued expenses', 'مصروفات مستحقة', T::Liability, '21', null, CF::Operating)
            ->leaf('2104', 'Customer advances', 'عملاء - دفعات مقدمة', T::Liability, '21', null, CF::Operating)
            ->leaf('2105', 'VAT output', 'ضريبة القيمة المضافة - مخرجات', T::Liability, '21', R::VatOutput, CF::Operating)
            ->leaf('2106', 'VAT due to the tax authority', 'ضريبة القيمة المضافة - مستحقة السداد', T::Liability, '21', null, CF::Operating)
            ->leaf('2107', 'Table tax payable', 'ضريبة الجدول مستحقة', T::Liability, '21', null, CF::Operating)
            ->leaf('2108', 'Withholding tax payable (deducted from suppliers)', 'ضريبة الخصم والإضافة - دائنة', T::Liability, '21', R::WithholdingTaxPayable, CF::Operating)
            ->leaf('2109', 'Payroll tax payable', 'ضريبة كسب العمل مستحقة', T::Liability, '21', null, CF::Operating)
            ->leaf('2110', 'Social insurance payable', 'التأمينات الاجتماعية مستحقة', T::Liability, '21', null, CF::Operating)
            ->leaf('2111', 'Salaries payable', 'مرتبات مستحقة', T::Liability, '21', null, CF::Operating)
            ->leaf('2112', 'Income tax payable', 'ضريبة الدخل مستحقة', T::Liability, '21', null, CF::Operating)
            ->leaf('2113', 'Stamp duty payable', 'ضريبة الدمغة مستحقة', T::Liability, '21', null, CF::Operating)
            ->leaf('2114', 'Short-term loans and bank overdrafts', 'قروض قصيرة الأجل وسحب على المكشوف', T::Liability, '21', null, CF::Financing)
            ->leaf('2115', 'Other credit balances', 'أرصدة دائنة أخرى', T::Liability, '21', null, CF::Operating)
            ->header('22', 'Non-current liabilities', 'الخصوم طويلة الأجل', T::Liability, '2')
            ->leaf('2201', 'Long-term loans', 'قروض طويلة الأجل', T::Liability, '22', null, CF::Financing)
            ->leaf('2202', 'Provisions', 'مخصصات', T::Liability, '22', null, CF::Operating)
            // 3 — Equity
            ->header('3', 'Equity', 'حقوق الملكية', T::Equity)
            ->leaf('3101', 'Issued capital', 'رأس المال المصدر', T::Equity, '3', null, CF::Financing)
            ->leaf('3102', 'Legal reserve', 'الاحتياطي القانوني', T::Equity, '3')
            ->leaf('3103', 'Other reserves', 'احتياطيات أخرى', T::Equity, '3')
            ->leaf('3104', 'Retained earnings', 'الأرباح المرحلة', T::Equity, '3', R::RetainedEarnings)
            ->leaf('3105', "Partners' current accounts", 'الحسابات الجارية للشركاء', T::Equity, '3', null, CF::Financing)
            ->leaf('3106', 'Opening balance equity', 'أرصدة افتتاحية - حقوق الملكية', T::Equity, '3', R::OpeningBalanceEquity)
            // 4 — Revenue
            ->header('4', 'Revenue', 'الإيرادات', T::Revenue)
            ->leaf('4101', 'Sales', 'المبيعات', T::Revenue, '4', R::SalesRevenue)
            ->leaf('4102', 'Sales returns and allowances', 'مردودات ومسموحات المبيعات', T::Revenue, '4')
            ->leaf('4103', 'Service revenue', 'إيرادات الخدمات', T::Revenue, '4')
            ->leaf('4104', 'Other operating revenue', 'إيرادات تشغيل أخرى', T::Revenue, '4')
            // 5 — Cost of sales
            ->header('5', 'Cost of sales', 'تكلفة المبيعات', T::Expense)
            ->leaf('5101', 'Cost of goods sold', 'تكلفة البضاعة المباعة', T::Expense, '5', R::CostOfGoodsSold)
            ->leaf('5102', 'Purchases', 'المشتريات', T::Expense, '5')
            ->leaf('5103', 'Purchase returns and allowances', 'مردودات ومسموحات المشتريات', T::Expense, '5')
            ->leaf('5104', 'Direct labour', 'أجور مباشرة', T::Expense, '5')
            // 6 — Operating expenses
            ->header('6', 'Operating expenses', 'المصروفات التشغيلية', T::Expense)
            ->leaf('6101', 'Salaries and wages', 'مرتبات وأجور', T::Expense, '6')
            ->leaf('6102', 'Social insurance (employer share)', 'التأمينات الاجتماعية - حصة صاحب العمل', T::Expense, '6')
            ->leaf('6103', 'Rent', 'إيجارات', T::Expense, '6')
            ->leaf('6104', 'Electricity, water and gas', 'كهرباء ومياه وغاز', T::Expense, '6')
            ->leaf('6105', 'Communications', 'اتصالات', T::Expense, '6')
            ->leaf('6106', 'Marketing and advertising', 'دعاية وإعلان', T::Expense, '6')
            ->leaf('6107', 'Professional fees', 'أتعاب مهنية', T::Expense, '6')
            ->leaf('6108', 'Travel and transport', 'سفر وانتقالات', T::Expense, '6')
            ->leaf('6109', 'Repairs and maintenance', 'صيانة وإصلاح', T::Expense, '6')
            ->leaf('6110', 'Depreciation expense', 'مصروف الإهلاك', T::Expense, '6', R::DepreciationExpense)
            ->leaf('6111', 'Bad debt expense', 'ديون معدومة', T::Expense, '6', R::BadDebtExpense)
            ->leaf('6112', 'Bank charges', 'مصاريف بنكية', T::Expense, '6', R::BankCharges)
            ->leaf('6113', 'Stamp duty and government fees', 'رسوم ودمغات وأعباء حكومية', T::Expense, '6')
            ->leaf('6114', 'Other operating expenses', 'مصروفات تشغيل أخرى', T::Expense, '6')
            // 7 — Other income and expenses
            ->header('71', 'Other income', 'إيرادات أخرى', T::Revenue)
            ->leaf('7101', 'Interest income', 'فوائد دائنة', T::Revenue, '71')
            ->leaf('7102', 'Gain on disposal of fixed assets', 'أرباح بيع أصول ثابتة', T::Revenue, '71')
            ->leaf('7103', 'Miscellaneous income', 'إيرادات متنوعة', T::Revenue, '71')
            ->header('72', 'Other expenses', 'مصروفات أخرى', T::Expense)
            ->leaf('7201', 'Realized foreign exchange gain/loss', 'فروق عملة محققة', T::Expense, '72', R::RealizedFxGainLoss)
            ->leaf('7202', 'Unrealized foreign exchange gain/loss', 'فروق عملة غير محققة', T::Expense, '72', R::UnrealizedFxGainLoss)
            ->leaf('7203', 'Rounding differences', 'فروق تقريب', T::Expense, '72', R::RoundingDifference)
            ->leaf('7204', 'Interest expense', 'فوائد مدينة', T::Expense, '72')
            ->leaf('7205', 'Loss on disposal of fixed assets', 'خسائر بيع أصول ثابتة', T::Expense, '72')
            // 8 — Income tax
            ->header('8', 'Income tax', 'ضرائب الدخل', T::Expense)
            ->leaf('8101', 'Income tax expense', 'مصروف ضريبة الدخل', T::Expense, '8')
            ->build();
    }
}
