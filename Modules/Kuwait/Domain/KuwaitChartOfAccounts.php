<?php

namespace Modules\Kuwait\Domain;

use Modules\Accounting\Domain\Enums\AccountRole as R;
use Modules\Accounting\Domain\Enums\AccountType as T;
use Modules\Accounting\Domain\Enums\CashFlowCategory as CF;
use Modules\Accounting\Domain\ValueObjects\AccountSeed;
use Modules\Localization\Domain\ChartBuilder;

/**
 * Chart of accounts for a Kuwaiti company reporting under IFRS. Kuwait levies no VAT today, so there
 * are no VAT accounts; instead it carries PIFSS, Zakat, NLST, KFAS and staff indemnity. Amounts are in
 * KWD (3 decimals). A template to start from: a licensed Kuwaiti accountant should review it first.
 */
final class KuwaitChartOfAccounts
{
    /** @return list<AccountSeed> */
    public static function seeds(): array
    {
        return (new ChartBuilder)
            // 1 — Assets
            ->header('1', 'Assets', 'الأصول', T::Asset)
            ->header('11', 'Non-current assets', 'الأصول غير المتداولة', T::Asset, '1')
            ->leaf('1101', 'Property, plant and equipment at cost', 'الممتلكات والمنشآت والمعدات بالتكلفة', T::Asset, '11', R::FixedAssetsCost, CF::Investing)
            ->leaf('1102', 'Land', 'الأراضي', T::Asset, '11', null, CF::Investing)
            ->leaf('1103', 'Buildings', 'المباني', T::Asset, '11', null, CF::Investing)
            ->leaf('1104', 'Machinery and equipment', 'الآلات والمعدات', T::Asset, '11', null, CF::Investing)
            ->leaf('1105', 'Vehicles', 'المركبات', T::Asset, '11', null, CF::Investing)
            ->leaf('1106', 'Furniture and fixtures', 'الأثاث والتجهيزات', T::Asset, '11', null, CF::Investing)
            ->leaf('1107', 'Computers and software', 'أجهزة الحاسب والبرمجيات', T::Asset, '11', null, CF::Investing)
            ->leaf('1108', 'Capital work in progress', 'أعمال رأسمالية قيد التنفيذ', T::Asset, '11', null, CF::Investing)
            ->leaf('1109', 'Accumulated depreciation', 'مجمع الاستهلاك', T::Asset, '11', R::AccumulatedDepreciation)
            ->leaf('1110', 'Right-of-use assets (IFRS 16)', 'أصول حق الاستخدام', T::Asset, '11', null, CF::Investing)
            ->leaf('1111', 'Intangible assets', 'الأصول غير الملموسة', T::Asset, '11', null, CF::Investing)
            ->leaf('1112', 'Investments', 'استثمارات', T::Asset, '11', null, CF::Investing)
            ->header('12', 'Current assets', 'الأصول المتداولة', T::Asset, '1')
            ->leaf('1201', 'Inventory', 'المخزون', T::Asset, '12', R::Inventory, CF::Operating)
            ->leaf('1202', 'Goods in transit', 'بضاعة في الطريق', T::Asset, '12', null, CF::Operating)
            ->leaf('1203', 'Trade receivables', 'العملاء', T::Asset, '12', R::AccountsReceivable, CF::Operating)
            ->leaf('1204', 'Allowance for expected credit losses', 'مخصص خسائر الائتمان المتوقعة', T::Asset, '12', null, CF::Operating)
            ->leaf('1205', 'Notes receivable', 'أوراق القبض', T::Asset, '12', R::NotesReceivable, CF::Operating)
            ->leaf('1206', 'Post-dated cheques under collection', 'شيكات آجلة تحت التحصيل', T::Asset, '12', null, CF::Operating)
            ->leaf('1207', 'Employee receivables', 'ذمم الموظفين', T::Asset, '12', null, CF::Operating)
            ->leaf('1208', 'Prepaid expenses', 'مصروفات مدفوعة مقدماً', T::Asset, '12', null, CF::Operating)
            ->leaf('1209', 'Contract retentions receivable', 'محتجزات مستحقة القبض', T::Asset, '12', null, CF::Operating)
            ->leaf('1210', 'Other receivables', 'ذمم مدينة أخرى', T::Asset, '12', null, CF::Operating)
            ->leaf('1211', 'Cash on hand', 'النقد في الصندوق', T::Asset, '12', R::Cash, CF::Cash)
            ->leaf('1212', 'Cash at banks', 'النقد لدى البنوك', T::Asset, '12', R::Bank, CF::Cash)
            ->leaf('1213', 'Petty cash (custody)', 'عهدة نقدية', T::Asset, '12', null, CF::Cash)
            // 2 — Liabilities
            ->header('2', 'Liabilities', 'الخصوم', T::Liability)
            ->header('21', 'Current liabilities', 'الخصوم المتداولة', T::Liability, '2')
            ->leaf('2101', 'Trade payables', 'الموردون', T::Liability, '21', R::AccountsPayable, CF::Operating)
            ->leaf('2102', 'Notes payable', 'أوراق الدفع', T::Liability, '21', R::NotesPayable, CF::Operating)
            ->leaf('2103', 'Accrued expenses', 'مصروفات مستحقة', T::Liability, '21', null, CF::Operating)
            ->leaf('2104', 'Customer advances', 'دفعات مقدمة من العملاء', T::Liability, '21', null, CF::Operating)
            ->leaf('2105', 'PIFSS payable', 'مستحقات المؤسسة العامة للتأمينات الاجتماعية', T::Liability, '21', null, CF::Operating)
            ->leaf('2106', 'Zakat payable', 'الزكاة المستحقة', T::Liability, '21', null, CF::Operating)
            ->leaf('2107', 'National Labour Support Tax (NLST) payable', 'ضريبة دعم العمالة الوطنية المستحقة', T::Liability, '21', null, CF::Operating)
            ->leaf('2108', 'KFAS contribution payable', 'حصة مؤسسة الكويت للتقدم العلمي المستحقة', T::Liability, '21', null, CF::Operating)
            ->leaf('2109', 'Salaries and wages payable', 'رواتب وأجور مستحقة', T::Liability, '21', null, CF::Operating)
            ->leaf('2110', 'Current portion of lease liabilities', 'الجزء المتداول من التزامات الإيجار', T::Liability, '21', null, CF::Financing)
            ->leaf('2111', 'Short-term loans and overdrafts', 'قروض قصيرة الأجل وسحب على المكشوف', T::Liability, '21', null, CF::Financing)
            ->leaf('2112', 'Other payables', 'ذمم دائنة أخرى', T::Liability, '21', null, CF::Operating)
            ->header('22', 'Non-current liabilities', 'الخصوم غير المتداولة', T::Liability, '2')
            ->leaf('2201', 'Staff indemnity (end-of-service) provision', 'مخصص مكافأة نهاية الخدمة', T::Liability, '22', null, CF::Operating)
            ->leaf('2202', 'Lease liabilities (IFRS 16)', 'التزامات الإيجار', T::Liability, '22', null, CF::Financing)
            ->leaf('2203', 'Long-term loans', 'قروض طويلة الأجل', T::Liability, '22', null, CF::Financing)
            // 3 — Equity
            ->header('3', 'Equity', 'حقوق الملكية', T::Equity)
            ->leaf('3101', 'Share capital', 'رأس المال', T::Equity, '3', null, CF::Financing)
            ->leaf('3102', 'Statutory reserve', 'الاحتياطي الإجباري', T::Equity, '3')
            ->leaf('3103', 'Voluntary reserve', 'الاحتياطي الاختياري', T::Equity, '3')
            ->leaf('3104', 'Retained earnings', 'الأرباح المرحلة', T::Equity, '3', R::RetainedEarnings)
            ->leaf('3105', "Partners' current accounts", 'الحسابات الجارية للشركاء', T::Equity, '3', null, CF::Financing)
            ->leaf('3106', 'Opening balance equity', 'أرصدة افتتاحية - حقوق الملكية', T::Equity, '3', R::OpeningBalanceEquity)
            // 4 — Revenue
            ->header('4', 'Revenue', 'الإيرادات', T::Revenue)
            ->leaf('4101', 'Sales', 'المبيعات', T::Revenue, '4', R::SalesRevenue)
            ->leaf('4102', 'Sales returns and discounts', 'مردودات المبيعات والخصومات', T::Revenue, '4')
            ->leaf('4103', 'Service revenue', 'إيرادات الخدمات', T::Revenue, '4')
            ->leaf('4104', 'Contract revenue', 'إيرادات العقود', T::Revenue, '4')
            // 5 — Cost of revenue
            ->header('5', 'Cost of revenue', 'تكلفة الإيرادات', T::Expense)
            ->leaf('5101', 'Cost of goods sold', 'تكلفة البضاعة المباعة', T::Expense, '5', R::CostOfGoodsSold)
            ->leaf('5102', 'Purchases', 'المشتريات', T::Expense, '5')
            ->leaf('5103', 'Purchase returns and discounts', 'مردودات المشتريات والخصومات', T::Expense, '5')
            ->leaf('5104', 'Direct labour', 'أجور مباشرة', T::Expense, '5')
            // 6 — Operating expenses
            ->header('6', 'General and administrative expenses', 'المصروفات العمومية والإدارية', T::Expense)
            ->leaf('6101', 'Salaries and wages', 'رواتب وأجور', T::Expense, '6')
            ->leaf('6102', 'PIFSS (employer contribution)', 'التأمينات الاجتماعية - حصة صاحب العمل', T::Expense, '6')
            ->leaf('6103', 'Rent', 'إيجارات', T::Expense, '6')
            ->leaf('6104', 'Utilities', 'كهرباء وماء', T::Expense, '6')
            ->leaf('6105', 'Communications', 'اتصالات', T::Expense, '6')
            ->leaf('6106', 'Marketing and advertising', 'تسويق وإعلان', T::Expense, '6')
            ->leaf('6107', 'Professional fees', 'أتعاب مهنية', T::Expense, '6')
            ->leaf('6108', 'Travel and transport', 'سفر وانتقالات', T::Expense, '6')
            ->leaf('6109', 'Repairs and maintenance', 'صيانة وإصلاح', T::Expense, '6')
            ->leaf('6110', 'Depreciation expense', 'مصروف الاستهلاك', T::Expense, '6', R::DepreciationExpense)
            ->leaf('6111', 'Bad debt / expected credit loss expense', 'مصروف الديون المعدومة وخسائر الائتمان', T::Expense, '6', R::BadDebtExpense)
            ->leaf('6112', 'Bank charges', 'مصاريف بنكية', T::Expense, '6', R::BankCharges)
            ->leaf('6113', 'Government fees (residency, work permits, licences)', 'رسوم حكومية (إقامات وتصاريح عمل وتراخيص)', T::Expense, '6')
            ->leaf('6114', 'Staff indemnity expense', 'مصروف مكافأة نهاية الخدمة', T::Expense, '6')
            ->leaf('6115', 'Other expenses', 'مصروفات أخرى', T::Expense, '6')
            // 7 — Other income and expenses
            ->header('71', 'Other income', 'إيرادات أخرى', T::Revenue)
            ->leaf('7101', 'Interest income', 'إيرادات فوائد', T::Revenue, '71')
            ->leaf('7102', 'Gain on disposal of property, plant and equipment', 'أرباح بيع ممتلكات ومنشآت ومعدات', T::Revenue, '71')
            ->leaf('7103', 'Miscellaneous income', 'إيرادات متنوعة', T::Revenue, '71')
            ->header('72', 'Other expenses', 'مصروفات أخرى', T::Expense)
            ->leaf('7201', 'Realized foreign exchange gain/loss', 'فروق عملة محققة', T::Expense, '72', R::RealizedFxGainLoss)
            ->leaf('7202', 'Unrealized foreign exchange gain/loss', 'فروق عملة غير محققة', T::Expense, '72', R::UnrealizedFxGainLoss)
            ->leaf('7203', 'Rounding differences', 'فروق تقريب', T::Expense, '72', R::RoundingDifference)
            ->leaf('7204', 'Finance costs', 'تكاليف تمويل', T::Expense, '72')
            ->leaf('7205', 'Loss on disposal of property, plant and equipment', 'خسائر بيع ممتلكات ومنشآت ومعدات', T::Expense, '72')
            // 8 — Statutory levies and tax
            ->header('8', 'Zakat, NLST, KFAS and income tax', 'الزكاة والضرائب والحصص القانونية', T::Expense)
            ->leaf('8101', 'Zakat expense', 'مصروف الزكاة', T::Expense, '8')
            ->leaf('8102', 'NLST expense', 'مصروف ضريبة دعم العمالة الوطنية', T::Expense, '8')
            ->leaf('8103', 'KFAS contribution expense', 'مصروف حصة مؤسسة الكويت للتقدم العلمي', T::Expense, '8')
            ->leaf('8104', 'Income tax expense (foreign-owned share)', 'مصروف ضريبة الدخل (حصة الأجانب)', T::Expense, '8')
            ->build();
    }
}
