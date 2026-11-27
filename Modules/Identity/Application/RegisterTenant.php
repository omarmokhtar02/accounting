<?php

namespace Modules\Identity\Application;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Localization\Application\CreateCompany;
use Modules\Shared\Domain\Contracts\TrialStarter;
use Modules\Tenancy\Domain\Models\Tenant;
use Modules\Tenancy\Infrastructure\TenantContext;
use Spatie\Permission\PermissionRegistrar;

final class RegisterTenant
{
    public function __construct(
        private readonly TenantRoleProvisioner $roles,
        private readonly TenantContext $context,
        private readonly CreateCompany $createCompany,
        private readonly TrialStarter $trialStarter,
    ) {}

    /**
     * Creates a tenant, its owner user and its first company (for the given country, with that
     * country's chart of accounts) in one transaction.
     */
    public function handle(string $userName, string $email, string $hashedPassword, string $companyName, string $countryCode, string $locale): User
    {
        return DB::transaction(function () use ($userName, $email, $hashedPassword, $companyName, $countryCode, $locale): User {
            $tenant = Tenant::create(['name' => $companyName, 'locale' => $locale]);

            $user = new User([
                'name' => $userName,
                'email' => $email,
                'password' => $hashedPassword,
                'locale' => $locale,
            ]);
            $user->tenant_id = $tenant->id;
            $user->save();

            $this->roles->provision($tenant->id);
            $this->trialStarter->startFor($tenant->id);

            $this->context->setTenant($tenant);
            $company = $this->createCompany->handle($companyName, null, $countryCode);
            $company->users()->attach($user->id);

            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
            $user->assignRole('owner');

            return $user->refresh();
        });
    }
}
