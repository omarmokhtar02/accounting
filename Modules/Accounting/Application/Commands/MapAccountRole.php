<?php

namespace Modules\Accounting\Application\Commands;

use Modules\Accounting\Application\AccountRoleResolver;
use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Exceptions\AccountRoleError;
use Modules\Accounting\Domain\Models\Account;
use Modules\Accounting\Domain\Models\AccountMapping;
use Modules\Shared\Application\RecordAudit;
use Modules\Tenancy\Infrastructure\TenantContext;

final class MapAccountRole
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly RecordAudit $audit,
        private readonly AccountRoleResolver $resolver,
    ) {}

    /**
     * Points a role at an account. The account must be an active, postable account of the right type.
     */
    public function handle(AccountRole $role, Account $account): AccountMapping
    {
        $companyId = $this->context->companyId();

        if ($account->company_id !== $companyId) {
            // Tenant scope already hides foreign accounts; this guards a company mix-up inside a tenant.
            throw AccountRoleError::notMapped($role);
        }

        if ($account->type !== $role->expectedType()) {
            throw AccountRoleError::wrongType($role, $role->expectedType());
        }

        if (! $account->is_postable || ! $account->isActive()) {
            throw AccountRoleError::notPostable();
        }

        $existing = AccountMapping::query()->where('company_id', $companyId)->where('role', $role->value)->first();

        $mapping = AccountMapping::updateOrCreate(
            ['company_id' => $companyId, 'role' => $role->value],
            ['account_id' => $account->id],
        );

        $this->resolver->forget();
        $this->audit->handle('account_mapping', $mapping->id, $existing ? 'remapped' : 'mapped',
            $existing ? ['account_id' => $existing->account_id] : null,
            ['role' => $role->value, 'account_id' => $account->id]);

        return $mapping;
    }
}
