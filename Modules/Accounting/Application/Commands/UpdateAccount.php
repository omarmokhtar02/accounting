<?php

namespace Modules\Accounting\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Application\DTOs\UpdateAccountData;
use Modules\Accounting\Domain\Exceptions\InvalidAccountChange;
use Modules\Accounting\Domain\Models\Account;
use Modules\Accounting\Domain\Models\AccountMapping;
use Modules\Accounting\Domain\Models\JournalLine;
use Modules\Shared\Application\RecordAudit;

final class UpdateAccount
{
    public function __construct(private readonly RecordAudit $audit) {}

    /**
     * Code and type are structural: they cannot change once the account has ledger activity or plays a
     * role. Names, cash-flow category and (valid) re-parenting always can.
     */
    public function handle(Account $account, UpdateAccountData $data): Account
    {
        return DB::transaction(function () use ($account, $data): Account {
            $account = Account::query()->lockForUpdate()->findOrFail($account->id);
            $used = $this->isUsed($account);

            if ($used && $data->code !== $account->code) {
                throw InvalidAccountChange::lockedAfterUse('code');
            }

            if ($used && $data->type !== $account->type) {
                throw InvalidAccountChange::lockedAfterUse('type');
            }

            if ($data->isPostable && $account->children()->exists()) {
                throw InvalidAccountChange::hasChildren();
            }

            if (! $data->isPostable && $used) {
                throw InvalidAccountChange::lockedAfterUse('is_postable');
            }

            $this->assertValidParent($account, $data);

            $old = $account->only(['code', 'name', 'name_ar', 'type', 'parent_id', 'is_postable', 'cash_flow_category']);

            $account->update([
                'code' => $data->code,
                'name' => $data->name,
                'name_ar' => $data->nameAr,
                'type' => $data->type,
                'parent_id' => $data->parentId,
                'is_postable' => $data->isPostable,
                'cash_flow_category' => $data->cashFlowCategory,
            ]);

            $this->audit->handle('account', $account->id, 'updated', $this->plain($old), $this->plain($account->only(array_keys($old))));

            return $account;
        });
    }

    private function isUsed(Account $account): bool
    {
        return JournalLine::query()->where('account_id', $account->id)->exists()
            || AccountMapping::query()->where('account_id', $account->id)->exists();
    }

    private function assertValidParent(Account $account, UpdateAccountData $data): void
    {
        if ($data->parentId === null) {
            return;
        }

        if ($data->parentId === $account->id) {
            throw InvalidAccountChange::parentCycle();
        }

        $parent = Account::query()->where('company_id', $account->company_id)->find($data->parentId)
            ?? throw InvalidAccountChange::parentNotAllowed();

        // A header account cannot also carry ledger lines, and children share their parent's type.
        if (JournalLine::query()->where('account_id', $parent->id)->exists() || $parent->type !== $data->type) {
            throw InvalidAccountChange::parentNotAllowed();
        }

        // Walk up from the new parent: meeting this account means the move would create a cycle.
        for ($cursor = $parent; $cursor !== null; $cursor = $cursor->parent_id ? Account::query()->find($cursor->parent_id) : null) {
            if ($cursor->id === $account->id) {
                throw InvalidAccountChange::parentCycle();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function plain(array $values): array
    {
        return array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $values);
    }
}
