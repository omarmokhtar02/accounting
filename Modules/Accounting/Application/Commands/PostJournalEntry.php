<?php

namespace Modules\Accounting\Application\Commands;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Application\DocumentSequencer;
use Modules\Accounting\Domain\Enums\JournalStatus;
use Modules\Accounting\Domain\Exceptions\AccountingPeriodClosed;
use Modules\Accounting\Domain\Exceptions\InvalidJournalState;
use Modules\Accounting\Domain\Models\AccountingPeriod;
use Modules\Accounting\Domain\Models\JournalEntry;
use Modules\Accounting\Domain\Services\JournalBalanceChecker;
use Modules\Shared\Application\RecordAudit;

final class PostJournalEntry
{
    public function __construct(
        private readonly JournalBalanceChecker $balance,
        private readonly DocumentSequencer $sequencer,
        private readonly RecordAudit $audit,
    ) {}

    public function handle(JournalEntry $entry, User $poster, bool $allowSelfPosting = false): JournalEntry
    {
        return DB::transaction(function () use ($entry, $poster, $allowSelfPosting): JournalEntry {
            $entry = JournalEntry::query()->lockForUpdate()->with('lines')->findOrFail($entry->id);

            $entry->status->assertCanTransitionTo(JournalStatus::Posted);

            if ($entry->created_by === $poster->id && ! $allowSelfPosting) {
                throw InvalidJournalState::selfPosting();
            }

            $this->balance->assertBalanced(
                $entry->lines->map(fn ($line) => ['debit' => $line->debit, 'credit' => $line->credit])->all(),
            );

            $period = $this->resolveOpenPeriod($entry);

            $entry->update([
                'status' => JournalStatus::Posted,
                'accounting_period_id' => $period->id,
                'number' => $this->sequencer->next($entry->company_id, 'journal', $entry->entry_date->year, 'JV'),
                'posted_by' => $poster->id,
                'posted_at' => now(),
            ]);

            $this->audit->handle('journal_entry', $entry->id, 'posted', ['status' => 'draft'], ['status' => 'posted', 'number' => $entry->number]);

            return $entry;
        });
    }

    private function resolveOpenPeriod(JournalEntry $entry): AccountingPeriod
    {
        $date = $entry->entry_date->toDateString();

        $period = AccountingPeriod::query()
            ->where('company_id', $entry->company_id)
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();

        if ($period === null) {
            throw AccountingPeriodClosed::notFound($date);
        }

        if (! $period->isOpen()) {
            throw AccountingPeriodClosed::forDate($date);
        }

        return $period;
    }
}
