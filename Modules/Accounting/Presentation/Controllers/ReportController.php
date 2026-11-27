<?php

namespace Modules\Accounting\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Accounting\Application\Reporting\GeneralLedgerReport;
use Modules\Accounting\Application\Reporting\TrialBalanceReport;
use Modules\Accounting\Domain\Models\Account;
use Modules\Shared\Domain\Contracts\CurrencyRegistry;
use Modules\Shared\Domain\Money;
use Modules\Shared\Infrastructure\Export\SpreadsheetExporter;
use Modules\Tenancy\Infrastructure\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function trialBalance(Request $request, TrialBalanceReport $report, SpreadsheetExporter $exporter, CurrencyRegistry $currencies, TenantContext $context): View|Response
    {
        [$from, $to] = $this->period($request);
        $hideZero = $request->boolean('hide_zero', true);
        $result = $report->build($from, $to, $hideZero);

        $format = (string) $request->query('format', 'html');

        if ($format !== 'html') {
            $this->authorizeExport($request);
            $currency = $currencies->get($result->currency);
            $number = fn (int $minor) => (float) (new Money($minor, $currency))->toDecimal();

            $rows = array_map(fn ($r) => [
                $r->code, $r->localizedName(), $r->type, $number($r->openingDebit()), $number($r->openingCredit()),
                $number($r->debit), $number($r->credit), $number($r->closingDebit()), $number($r->closingCredit()),
            ], $result->rows);

            $rows[] = ['', __('accounting.total'), '', $number(array_sum(array_map(fn ($r) => $r->openingDebit(), $result->rows))),
                $number(array_sum(array_map(fn ($r) => $r->openingCredit(), $result->rows))), $number($result->totalDebit()), $number($result->totalCredit()),
                $number($result->totalClosingDebit()), $number($result->totalClosingCredit())];

            return $exporter->download($format, "trial-balance_{$from->toDateString()}_{$to->toDateString()}", [
                __('accounting.code'), __('accounting.name'), __('accounting.type'), __('accounting.opening_debit'), __('accounting.opening_credit'),
                __('accounting.debit'), __('accounting.credit'), __('accounting.closing_debit'), __('accounting.closing_credit'),
            ], $rows);
        }

        return view('accounting::reports.trial-balance', ['report' => $result, 'from' => $from, 'to' => $to, 'hideZero' => $hideZero]);
    }

    public function generalLedger(Request $request, GeneralLedgerReport $report, SpreadsheetExporter $exporter, CurrencyRegistry $currencies, TenantContext $context): View|Response
    {
        [$from, $to] = $this->period($request);
        $accounts = Account::query()->where('company_id', $context->companyId())->where('is_postable', true)->orderBy('code')->get();
        $format = (string) $request->query('format', 'html');

        if (! $request->filled('account_id')) {
            return view('accounting::reports.general-ledger', ['accounts' => $accounts, 'result' => null, 'from' => $from, 'to' => $to]);
        }

        $account = Account::query()->where('company_id', $context->companyId())->findOrFail($request->integer('account_id'));

        if ($format !== 'html') {
            $this->authorizeExport($request);
            $result = $report->build($account, $from, $to, null);
            $currency = $currencies->get($result->currency);
            $number = fn (int $minor) => (float) (new Money($minor, $currency))->toDecimal();

            $rows = [['', '', __('accounting.opening_balance'), '', '', '', $number($result->openingBalance)]];
            foreach ($result->lines as $l) {
                $rows[] = [$l->date, $l->number ?? '', $l->entryDescription, $l->lineDescription ?? '', $number($l->debit), $number($l->credit), $number($l->runningBalance)];
            }
            $rows[] = ['', '', __('accounting.closing_balance'), '', $number($result->totalDebit()), $number($result->totalCredit()), $number($result->closingBalance())];

            return $exporter->download($format, "ledger_{$account->code}_{$from->toDateString()}_{$to->toDateString()}", [
                __('accounting.date'), __('accounting.number'), __('accounting.description'), __('accounting.line_description'),
                __('accounting.debit'), __('accounting.credit'), __('accounting.balance'),
            ], $rows);
        }

        return view('accounting::reports.general-ledger', ['accounts' => $accounts, 'result' => $report->build($account, $from, $to), 'from' => $from, 'to' => $to]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function period(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'format' => ['nullable', Rule::in(['html', ...SpreadsheetExporter::FORMATS])],
            'hide_zero' => ['nullable', 'boolean'],
            'account_id' => ['nullable', 'integer'],
        ]);

        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to']) : CarbonImmutable::today();
        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from']) : $to->startOfYear();

        return [$from, $to];
    }

    private function authorizeExport(Request $request): void
    {
        abort_unless($request->user()->can('reports.export'), 403);
    }
}
