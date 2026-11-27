<?php

namespace Modules\Accounting\Application\Commands;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Application\DocumentSequencer;
use Modules\Accounting\Domain\Enums\JournalStatus;
use Modules\Accounting\Domain\Exceptions\AccountingPeriodClosed;
use Modules\Accounting\Domain\Models\AccountingPeriod;
use Modules\Accounting\Domain\Models\JournalEntry;
use Modules\Shared\Application\RecordAudit;

final class ReverseJournalEntry
{
    public function __construct(
        private readonly DocumentSequencer $sequencer,
        private readonly RecordAudit $audit,
    ) {}

    /**
     * Creates a posted, equal-and-opposite entry dated $reversalDate. The original keeps its
     * lines and only gains its reversed status and link.
     */
    public function handle(JournalEntry $original, User $actor, CarbonImmutable $reversalDate, ?string $reason = null): JournalEntry
    {
        return DB::transaction(function () use ($original, $actor, $reversalDate, $reason): JournalEntry {
            $original = JournalEntry::query()->lockForUpdate()->with('lines')->findOrFail($original->id);

            $original->status->assertCanTransitionTo(JournalStatus::Reversed);

            $date = $reversalDate->toDateString();
            $period = AccountingPeriod::query()
                ->where('company_id', $original->company_id)
                ->whereDate('starts_on', '<=', $date)
                ->whereDate('ends_on', '>=', $date)
                ->first();

            if ($period === null) {
                throw AccountingPeriodClosed::notFound($date);
            }

            if (! $period->isOpen()) {
                throw AccountingPeriodClosed::forDate($date);
            }

            $reversal = JournalEntry::create([
                'company_id' => $original->company_id,
                'accounting_period_id' => $period->id,
                'number' => $this->sequencer->next($original->company_id, 'journal', $reversalDate->year, 'JV'),
                'entry_date' => $reversalDate,
                'description' => __('accounting.reversal_of', ['number' => $original->number]),
                'status' => JournalStatus::Posted,
                'currency' => $original->currency,
                'reverses_id' => $original->id,
                'created_by' => $actor->id,
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            foreach ($original->lines as $line) {
                $reversal->lines()->create([
                    'company_id' => $original->company_id,
                    'account_id' => $line->account_id,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'description' => $line->description,
                ]);
            }

            $original->update([
                'status' => JournalStatus::Reversed,
                'reversed_by_id' => $reversal->id,
            ]);

            $this->audit->handle('journal_entry', $original->id, 'reversed', ['status' => 'posted'], ['status' => 'reversed', 'reversal' => $reversal->number], $reason);

            return $reversal;
        });
    }
}
