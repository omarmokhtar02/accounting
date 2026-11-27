<?php

namespace Modules\Accounting\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Accounting\Application\Commands\CreateJournalEntry;
use Modules\Accounting\Application\Commands\PostJournalEntry;
use Modules\Accounting\Application\Commands\ReverseJournalEntry;
use Modules\Accounting\Application\DTOs\CreateJournalEntryData;
use Modules\Accounting\Application\DTOs\JournalLineData;
use Modules\Accounting\Domain\Exceptions\DomainException;
use Modules\Accounting\Domain\Models\Account;
use Modules\Accounting\Domain\Models\JournalEntry;
use Modules\Accounting\Presentation\Requests\StoreJournalEntryRequest;
use Modules\Shared\Domain\Contracts\CurrencyRegistry;
use Modules\Shared\Domain\Exceptions\InvalidMoneyAmount;
use Modules\Shared\Domain\Models\AuditLog;
use Modules\Shared\Domain\Money;
use Modules\Tenancy\Infrastructure\TenantContext;

class JournalEntryController extends Controller
{
    public function index(TenantContext $context): View
    {
        $entries = JournalEntry::query()
            ->where('company_id', $context->companyId())
            ->withSum('lines as total_debit', 'debit')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(25);

        return view('accounting::journal-entries.index', ['entries' => $entries]);
    }

    public function create(TenantContext $context): View
    {
        return view('accounting::journal-entries.create', [
            'accounts' => Account::query()
                ->where('company_id', $context->companyId())
                ->where('status', 'active')
                ->where('is_postable', true)
                ->orderBy('code')
                ->get(),
            'currencies' => array_keys(app(CurrencyRegistry::class)->all()),
            'baseCurrency' => $context->company()->base_currency,
        ]);
    }

    public function store(StoreJournalEntryRequest $request, CreateJournalEntry $createJournalEntry, TenantContext $context, CurrencyRegistry $currencies): RedirectResponse
    {
        $base = $context->company()->base_currency;

        try {
            $lines = collect($request->validated('lines'))->map(function (array $line) use ($currencies, $base): JournalLineData {
                $code = ($line['currency'] ?? '') ?: $base;
                $money = fn (string $key) => Money::fromDecimal(($line[$key] ?? '') ?: '0', $currencies->get($code))->minor;

                if ($code === $base) {
                    return new JournalLineData((int) $line['account_id'], $money('debit'), $money('credit'), $line['description'] ?? null);
                }

                return new JournalLineData(
                    (int) $line['account_id'], 0, 0, $line['description'] ?? null, $code,
                    $money('debit') ?: null, $money('credit') ?: null, ($line['rate'] ?? '') ?: null,
                );
            })->filter(fn (JournalLineData $line) => $line->debit > 0 || $line->credit > 0 || $line->txnDebit > 0 || $line->txnCredit > 0)->values()->all();
        } catch (InvalidMoneyAmount) {
            return back()->withInput()->withErrors(['lines' => __('accounting.import.bad_amount')]);
        }

        try {
            $entry = $createJournalEntry->handle(new CreateJournalEntryData(
                CarbonImmutable::parse($request->validated('entry_date')),
                $request->validated('description'),
                $lines,
                $request->validated('idempotency_key'),
            ));
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['lines' => $e->userMessage()]);
        }

        return redirect()->route('accounting.journal-entries.show', $entry)->with('status', __('accounting.journal_created'));
    }

    public function show(JournalEntry $journalEntry, TenantContext $context): View
    {
        abort_unless($journalEntry->company_id === $context->companyId(), 404);

        $journalEntry->load(['lines.account', 'creator', 'reverses', 'reversedBy']);

        $audit = AuditLog::query()
            ->where('entity_type', 'journal_entry')
            ->where('entity_id', $journalEntry->id)
            ->orderBy('occurred_at')
            ->get();

        return view('accounting::journal-entries.show', ['entry' => $journalEntry, 'audit' => $audit]);
    }

    public function post(Request $request, JournalEntry $journalEntry, PostJournalEntry $postJournalEntry, TenantContext $context): RedirectResponse
    {
        abort_unless($journalEntry->company_id === $context->companyId(), 404);

        try {
            $postJournalEntry->handle($journalEntry, $request->user(), $request->user()->can('journals.post_own'));
        } catch (DomainException $e) {
            return back()->withErrors(['journal' => $e->userMessage()]);
        }

        return back()->with('status', __('accounting.journal_posted'));
    }

    public function reverse(Request $request, JournalEntry $journalEntry, ReverseJournalEntry $reverseJournalEntry, TenantContext $context): RedirectResponse
    {
        abort_unless($journalEntry->company_id === $context->companyId(), 404);

        $validated = $request->validate([
            'reversal_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $reversal = $reverseJournalEntry->handle(
                $journalEntry,
                $request->user(),
                CarbonImmutable::parse($validated['reversal_date']),
                $validated['reason'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['journal' => $e->userMessage()]);
        }

        return redirect()->route('accounting.journal-entries.show', $reversal)->with('status', __('accounting.journal_reversed'));
    }
}
