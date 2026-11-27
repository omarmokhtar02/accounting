<?php

namespace Modules\Accounting\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Domain\Enums\AccountStatus;
use Modules\Accounting\Domain\Exceptions\InvalidAccountChange;
use Modules\Accounting\Domain\Models\Account;
use Modules\Accounting\Domain\Models\AccountMapping;
use Modules\Accounting\Domain\Models\JournalLine;
use Modules\Shared\Application\RecordAudit;

final class ChangeAccountStatus
{
    public function __construct(private readonly RecordAudit $audit) {}

    public function handle(Account $account, AccountStatus $target): Account
    {
        return DB::transaction(function () use ($account, $target): Account {
            $account = Account::query()->lockForUpdate()->findOrFail($account->id);
            $account->status->assertCanTransitionTo($target);

            if ($target !== AccountStatus::Active) {
                if (AccountMapping::query()->where('account_id', $account->id)->exists()) {
                    throw InvalidAccountChange::lockedAfterUse('role');
                }

                if ($account->children()->where('status', '!=', AccountStatus::Archived->value)->exists()) {
                    throw InvalidAccountChange::hasChildren();
                }
            }

            if ($target === AccountStatus::Archived) {
                $balance = (int) JournalLine::query()->where('account_id', $account->id)->sum('debit')
                    - (int) JournalLine::query()->where('account_id', $account->id)->sum('credit');

                if ($balance !== 0) {
                    throw InvalidAccountChange::archiveWithBalance();
                }
            }

            $previous = $account->status;
            $account->update(['status' => $target]);

            $this->audit->handle('account', $account->id, 'status_changed', ['status' => $previous->value], ['status' => $target->value]);

            return $account;
        });
    }
}
