<?php

namespace Modules\Accounting\Application;

use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Exceptions\AccountRoleError;
use Modules\Accounting\Domain\Models\Account;
use Modules\Accounting\Domain\Models\AccountMapping;
use Modules\Tenancy\Infrastructure\Exceptions\InvalidTenantContext;
use Modules\Tenancy\Infrastructure\TenantContext;

/**
 * Answers "which ledger account plays this role for the active company?". Operational modules
 * (invoices, payments, stock, assets) call this instead of knowing account codes.
 */
final class AccountRoleResolver
{
    /** @var array<int, array<string, int>> company id => role value => account id */
    private array $cache = [];

    public function __construct(private readonly TenantContext $context) {}

    public function accountId(AccountRole $role): int
    {
        $companyId = $this->context->companyId() ?? throw InvalidTenantContext::missing();

        $this->cache[$companyId] ??= AccountMapping::query()
            ->where('company_id', $companyId)
            ->pluck('account_id', 'role')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->cache[$companyId][$role->value] ?? throw AccountRoleError::notMapped($role);
    }

    public function account(AccountRole $role): Account
    {
        return Account::query()->findOrFail($this->accountId($role));
    }

    /**
     * @return array<string, int> role value => account id for every control account of the company
     */
    public function controlAccountIds(): array
    {
        $companyId = $this->context->companyId() ?? throw InvalidTenantContext::missing();

        return AccountMapping::query()
            ->where('company_id', $companyId)
            ->get()
            ->filter(fn (AccountMapping $m) => $m->role->isControl())
            ->mapWithKeys(fn (AccountMapping $m) => [$m->role->value => (int) $m->account_id])
            ->all();
    }

    public function forget(): void
    {
        $this->cache = [];
    }
}
