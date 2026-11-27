<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Modules\Accounting\Application\Commands\OpenFiscalYear;
use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Models\Account;
use Modules\Identity\Application\RegisterTenant;
use Modules\Tenancy\Infrastructure\TenantContext;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates N isolated tenants (one owner each, password "password") for load testing.
 * Use a throwaway database: the data is real and the posted ledger is append-only.
 */
class SeedLoadTestData extends Command
{
    protected $signature = 'loadtest:seed {--tenants=20 : Number of tenants (= users) to create} {--prefix=loadtest}';

    protected $description = 'Seed tenants, users, a fiscal year and accounts for load testing (never in production)';

    public function handle(RegisterTenant $registerTenant, TenantContext $context): int
    {
        if (app()->isProduction()) {
            $this->error('Refusing to seed load-test data in production.');

            return self::FAILURE;
        }

        $count = (int) $this->option('tenants');
        $prefix = (string) $this->option('prefix');
        $hash = Hash::make('password');

        $this->output->progressStart($count);

        for ($i = 1; $i <= $count; $i++) {
            $email = "{$prefix}{$i}@example.com";

            if (User::where('email', $email)->exists()) {
                $this->output->progressAdvance();

                continue;
            }

            $user = $registerTenant->handle("Load User {$i}", $email, $hash, "Load Co {$i}", 'EG', 'en');
            $context->setTenant($user->tenant);
            $context->setCompany($user->companies()->first());
            app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
            Auth::login($user);

            app(OpenFiscalYear::class)->handle('2026', CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));

            foreach (['1000' => AccountType::Asset, '1100' => AccountType::Asset, '2000' => AccountType::Liability, '4000' => AccountType::Revenue, '5000' => AccountType::Expense] as $code => $type) {
                Account::create(['company_id' => $context->companyId(), 'code' => $code, 'name' => "Account {$code}", 'type' => $type]);
            }

            Auth::logout();
            $context->clear();
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();
        $this->info("Ready: {$prefix}1..{$count}@example.com / password");

        return self::SUCCESS;
    }
}
