<?php

namespace Modules\SaudiArabia\Domain;

use Modules\Accounting\Domain\Enums\AccountRole as R;
use Modules\Accounting\Domain\Enums\AccountType as T;
use Modules\Accounting\Domain\Enums\CashFlowCategory as CF;
use Modules\Accounting\Domain\ValueObjects\AccountSeed;
use Modules\Localization\Domain\ChartBuilder;

/**
 * Chart of accounts for a Saudi company reporting under IFRS as endorsed in Saudi Arabia (SOCPA).
 * Includes VAT, Zakat, GOSI, end-of-service benefits and IFRS 16 leases. A template to start from:
 * a licensed Saudi accountant should review it before first use.
 */
final class SaudiChartOfAccounts
{
    /** @return list<AccountSeed> */
    public static function seeds(): array
    {
        return (new ChartBuilder)
            // 1 — Assets
            ->header('1', 'Assets', 'الأصول', T::Asset)
            ->header('11', 'Non-current assets', 'الأصول غير المتداولة', T::Asset, '1')
            ->leaf('1101', 'Property, plant and equipment at cost', 'العقارات والآلات والمعدات بالتكلفة', T::Asset, '11', R::FixedAssetsCost, CF::Investing)
            ->leaf('1102', 'Land', 'الأراضي', T::Asset, '11', null, CF::Investing)
            ->leaf('1103', 'Buildings', 'المباني', T::Asset, '11', null, CF::Investing)
            ->leaf('1104', 'Machinery and equipment', 'الآلات والمعدات', T::Asset, '11', null, CF::Investing)
            ->leaf('1105', 'Motor vehicles', 'السيارات', T::Asset, '11', null, CF::Investing)
            ->leaf('1106', 'Furniture and fixtures', 'الأثاث والتجهيزات', T::Asset, '11', null, CF::Investing)
            ->leaf('1107', 'Computers and software', 'أجهزة الحاسب والبرمجيات', T::Asset, '11', null, CF::Investing)
            ->leaf('1108', 'Capital work in progress', 'أعمال رأسمالية تحت التنفيذ', T::Asset, '11', null, CF::Investing)
            ->leaf('1109', 'Accumulated depreciation', 'مجمع الإهلاك', T::Asset, '11', R::AccumulatedDepreciation)
            ->leaf('1110', 'Right-of-use assets (IFRS 16)', 'أصول حق الاستخدام', T::Asset, '11', null, CF::Investing)
            ->leaf('1111', 'Intangible assets', 'الأصول غير الملموسة', T::Asset, '11', null, CF::Investing)
            ->leaf('1112', 'Long-term investments', 'استثمارات طويلة الأجل', T::Asset, '11', null, CF::Investing)
            ->header('12', 'Current assets', 'الأصول المتداولة', T::Asset, '1')
            ->leaf('1201', 'Inventory', 'المخزون', T::Asset, '12', R::Inventory, CF::Operating)
            ->leaf('1202', 'Goods in transit', 'بضاعة بالطريق', T::Asset, '12', null, CF::Operating)
            ->leaf('1203', 'Trade receivables', 'العملاء', T::Asset, '12', R::AccountsReceivable, CF::Operating)
            ->leaf('1204', 'Allowance for expected credit losses', 'مخصص خسائر الائتمان المتوقعة', T::Asset, '12', null, CF::Operating)
            ->leaf('1205', 'Notes receivable', 'أوراق القبض', T::Asset, '12', R::NotesReceivable, CF::Operating)
            ->leaf('1206', 'Cheques under collection', 'شيكات تحت التحصيل', T::Asset, '12', null, CF::Operating)
            ->leaf('1207', 'Employee receivables', 'ذمم الموظفين', T::Asset, '12', null, CF::Operating)
            ->leaf('1208', 'Prepaid expenses', 'مصروفات مدفوعة مقدماً', T::Asset, '12', null, CF::Operating)
            ->leaf('1209', 'Other receivables', 'ذمم مدينة أخرى', T::Asset, '12', null, CF::Operating)
            ->leaf('1210', 'VAT input', 'ضريبة القيمة المضافة - مدخلات', T::Asset, '12', R::VatInput, CF::Operating)
            ->leaf('1211', 'Withholding tax recoverable', 'ضريبة استقطاع مستردة', T::Asset, '12', R::WithholdingTaxReceivable, CF::Operating)
            ->leaf('1212', 'Cash on hand', 'النقدية بالصندوق', T::Asset, '12', R::Cash, CF::Cash)
            ->leaf('1213', 'Cash at banks', 'النقد لدى البنوك', T::Asset, '12', R::Bank, CF::Cash)
            ->leaf('1214', 'Petty cash (custody)', 'عهدة نقدية', T::Asset, '12', null, CF::Cash)
            // 2 — Liabilities
            ->header('2', 'Liabilities', 'الخصوم', T::Liability)
            ->header('21', 'Current liabilities', 'الخصوم المتداولة', T::Liability, '2')
            ->leaf('2101', 'Trade payables', 'الموردون', T::Liability, '21', R::AccountsPayable, CF::Operating)
            ->leaf('2102', 'Notes payable', 'أوراق الدفع', T::Liability, '21', R::NotesPayable, CF::Operating)
            ->leaf('2103', 'Accrued expenses', 'مصروفات مستحقة', T::Liability, '21', null, CF::Operating)
            ->leaf('2104', 'Customer advances (contract liabilities)', 'دفعات مقدمة من العملاء', T::Liability, '21', null, CF::Operating)
            ->leaf('2105', 'VAT output', 'ضريبة القيمة المضافة - مخرجات', T::Liability, '21', R::VatOutput, CF::Operating)
            ->leaf('2106', 'VAT payable to ZATCA', 'ضريبة القيمة المضافة المستحقة لهيئة الزكاة والضريبة والجمارك', T::Liability, '21', null, CF::Operating)
            ->leaf('2107', 'Zakat payable', 'الزكاة المستحقة', T::Liability, '21', null, CF::Operating)
            ->leaf('2108', 'Income tax payable', 'ضريبة الدخل المستحقة', T::Liability, '21', null, CF::Operating)
            ->leaf('2109', 'Withholding tax payable', 'ضريبة الاستقطاع المستحقة', T::Liability, '21', R::WithholdingTaxPayable, CF::Operating)
            ->leaf('2110', 'GOSI payable', 'مستحقات التأمينات الاجتماعية (GOSI)', T::Liability, '21', null, CF::Operating)
            ->leaf('2111', 'Salaries and wages payable', 'رواتب وأجور مستحقة', T::Liability, '21', null, CF::Operating)
            ->leaf('2112', 'Current portion of lease liabilities', 'الجزء المتداول من التزامات الإيجار', T::Liability, '21', null, CF::Financing)
            ->leaf('2113', 'Short-term loans and overdrafts', 'قروض قصيرة الأجل وسحب على المكشوف', T::Liability, '21', null, CF::Financing)
            ->leaf('2114', 'Other payables', 'ذمم دائنة أخرى', T::Liability, '21', null, CF::Operating)
            ->header('22', 'Non-current liabilities', 'الخصوم غير المتداولة', T::Liability, '2')
            ->leaf('2201', 'End-of-service benefits provision', 'مخصص مكافأة نهاية الخدمة', T::Liability, '22', null, CF::Operating)
            ->leaf('2202', 'Lease liabilities (IFRS 16)', 'التزامات الإيجار', T::Liability, '22', null, CF::Financing)
            ->leaf('2203', 'Long-term loans', 'قروض طويلة الأجل', T::Liability, '22', null, CF::Financing)
            // 3 — Equity
            ->header('3', 'Equity', 'حقوق الملكية', T::Equity)
            ->leaf('3101', 'Share capital', 'رأس المال', T::Equity, '3', null, CF::Financing)
            ->leaf('3102', 'Statutory reserve', 'الاحتياطي النظامي', T::Equity, '3')
            ->leaf('3103', 'Other reserves', 'احتياطيات أخرى', T::Equity, '3')
            ->leaf('3104', 'Retained earnings', 'الأرباح المبقاة', T::Equity, '3', R::RetainedEarnings)
            ->leaf('3105', "Owners' / partners' current accounts", 'الحسابات الجارية للشركاء', T::Equity, '3', null, CF::Financing)
            ->leaf('3106', 'Opening balance equity', 'أرصدة افتتاحية - حقوق الملكية', T::Equity, '3', R::OpeningBalanceEquity)
            // 4 — Revenue
            ->header('4', 'Revenue', 'الإيرادات', T::Revenue)
            ->leaf('4101', 'Sales', 'المبيعات', T::Revenue, '4', R::SalesRevenue)
            ->leaf('4102', 'Sales returns and discounts', 'مردودات المبيعات والخصومات', T::Revenue, '4')
            ->leaf('4103', 'Service revenue', 'إيرادات الخدمات', T::Revenue, '4')
            ->leaf('4104', 'Other operating revenue', 'إيرادات تشغيلية أخرى', T::Revenue, '4')
            // 5 — Cost of revenue
            ->header('5', 'Cost of revenue', 'تكلفة الإيرادات', T::Expense)
            ->leaf('5101', 'Cost of goods sold', 'تكلفة البضاعة المباعة', T::Expense, '5', R::CostOfGoodsSold)
            ->leaf('5102', 'Purchases', 'المشتريات', T::Expense, '5')
            ->leaf('5103', 'Purchase returns and discounts', 'مردودات المشتريات والخصومات', T::Expense, '5')
            ->leaf('5104', 'Direct labour', 'أجور مباشرة', T::Expense, '5')
            // 6 — Operating expenses
            ->header('6', 'Operating expenses', 'المصروفات التشغيلية', T::Expense)
            ->leaf('6101', 'Salaries and wages', 'رواتب وأجور', T::Expense, '6')
            ->leaf('6102', 'GOSI (employer contribution)', 'التأمينات الاجتماعية - حصة صاحب العمل', T::Expense, '6')
            ->leaf('6103', 'Rent', 'إيجارات', T::Expense, '6')
            ->leaf('6104', 'Utilities', 'كهرباء ومياه', T::Expense, '6')
            ->leaf('6105', 'Communications', 'اتصالات', T::Expense, '6')
            ->leaf('6106', 'Marketing and advertising', 'تسويق وإعلان', T::Expense, '6')
            ->leaf('6107', 'Professional fees', 'أتعاب مهنية', T::Expense, '6')
            ->leaf('6108', 'Travel and transport', 'سفر وانتقالات', T::Expense, '6')
            ->leaf('6109', 'Repairs and maintenance', 'صيانة وإصلاح', T::Expense, '6')
            ->leaf('6110', 'Depreciation expense', 'مصروف الإهلاك', T::Expense, '6', R::DepreciationExpense)
            ->leaf('6111', 'Bad debt / expected credit loss expense', 'مصروف الديون المعدومة وخسائر الائتمان', T::Expense, '6', R::BadDebtExpense)
            ->leaf('6112', 'Bank charges', 'مصاريف بنكية', T::Expense, '6', R::BankCharges)
            ->leaf('6113', 'Government fees (Iqama, visas, licences)', 'رسوم حكومية (إقامات وتأشيرات وتراخيص)', T::Expense, '6')
            ->leaf('6114', 'End-of-service benefits expense', 'مصروف مكافأة نهاية الخدمة', T::Expense, '6')
            ->leaf('6115', 'Other operating expenses', 'مصروفات تشغيلية أخرى', T::Expense, '6')
            // 7 — Other income and expenses
            ->header('71', 'Other income', 'إيرادات أخرى', T::Revenue)
            ->leaf('7101', 'Interest income', 'إيرادات فوائد', T::Revenue, '71')
            ->leaf('7102', 'Gain on disposal of property, plant and equipment', 'أرباح بيع ممتلكات وآلات ومعدات', T::Revenue, '71')
            ->leaf('7103', 'Miscellaneous income', 'إيرادات متنوعة', T::Revenue, '71')
            ->header('72', 'Other expenses', 'مصروفات أخرى', T::Expense)
            ->leaf('7201', 'Realized foreign exchange gain/loss', 'فروق عملة محققة', T::Expense, '72', R::RealizedFxGainLoss)
            ->leaf('7202', 'Unrealized foreign exchange gain/loss', 'فروق عملة غير محققة', T::Expense, '72', R::UnrealizedFxGainLoss)
            ->leaf('7203', 'Rounding differences', 'فروق تقريب', T::Expense, '72', R::RoundingDifference)
            ->leaf('7204', 'Finance costs', 'تكاليف تمويل', T::Expense, '72')
            ->leaf('7205', 'Loss on disposal of property, plant and equipment', 'خسائر بيع ممتلكات وآلات ومعدات', T::Expense, '72')
            // 8 — Zakat and income tax
            ->header('8', 'Zakat and income tax', 'الزكاة وضريبة الدخل', T::Expense)
            ->leaf('8101', 'Zakat expense', 'مصروف الزكاة', T::Expense, '8')
            ->leaf('8102', 'Income tax expense', 'مصروف ضريبة الدخل', T::Expense, '8')
            ->build();
    }
}
