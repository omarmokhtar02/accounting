<?php

namespace Modules\Identity\Application;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Shared\Domain\Contracts\UsageLimits;
use Modules\Tenancy\Domain\Models\Company;
use Modules\Tenancy\Infrastructure\TenantContext;
use Spatie\Permission\PermissionRegistrar;

final class CreateTenantUser
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly UsageLimits $limits,
    ) {}

    /**
     * Adds a user to the current tenant with one role and access to the given companies.
     * Company ids are re-checked against the tenant scope, so foreign ids are ignored.
     *
     * @param  list<int>  $companyIds
     */
    public function handle(string $name, string $email, string $hashedPassword, string $role, array $companyIds): User
    {
        $this->limits->assertCanAddUser((int) $this->context->tenantId());

        return DB::transaction(function () use ($name, $email, $hashedPassword, $role, $companyIds): User {
            $user = new User(['name' => $name, 'email' => $email, 'password' => $hashedPassword, 'locale' => app()->getLocale()]);
            $user->tenant_id = $this->context->tenantId();
            $user->save();

            $user->companies()->sync(Company::query()->whereIn('id', $companyIds)->pluck('id')->all());

            app(PermissionRegistrar::class)->setPermissionsTeamId($this->context->tenantId());
            $user->assignRole($role);

            return $user->refresh();
        });
    }
}
