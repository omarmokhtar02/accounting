<?php

namespace Modules\Accounting\Application\Commands;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Domain\Enums\JournalStatus;
use Modules\Accounting\Domain\Exceptions\InvalidJournalState;
use Modules\Accounting\Domain\Models\AccountingPeriod;
use Modules\Accounting\Domain\Models\JournalEntry;
use Modules\Shared\Application\RecordAudit;

final class ClosePeriod
{
    public function __construct(private readonly RecordAudit $audit) {}

    public function handle(AccountingPeriod $period, User $actor): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $actor): AccountingPeriod {
            $period = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->id);

            $hasDrafts = JournalEntry::query()
                ->where('company_id', $period->company_id)
                ->where('status', JournalStatus::Draft)
                ->whereDate('entry_date', '>=', $period->starts_on->toDateString())
                ->whereDate('entry_date', '<=', $period->ends_on->toDateString())
                ->exists();

            if ($hasDrafts) {
                throw InvalidJournalState::unpostedDrafts();
            }

            $period->update([
                'status' => AccountingPeriod::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by' => $actor->id,
            ]);

            $this->audit->handle('accounting_period', $period->id, 'closed', ['status' => 'open'], ['status' => 'closed']);

            return $period;
        });
    }
}
