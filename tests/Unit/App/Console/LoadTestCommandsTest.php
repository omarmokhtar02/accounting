<?php

use App\Console\Commands\BenchmarkLedger;
use App\Console\Commands\SeedLoadTestData;
use App\Models\User;
use Modules\Accounting\Domain\Models\JournalEntry;
use Modules\Tenancy\Domain\Models\Company;
use Modules\Tenancy\Domain\Models\Tenant;

covers(SeedLoadTestData::class, BenchmarkLedger::class);

afterEach(function () {
    app()['env'] = 'testing';
});

describe('loadtest:seed', function () {
    it('creates isolated tenants with an owner, a fiscal year and accounts', function () {
        $this->artisan('loadtest:seed', ['--tenants' => 2, '--prefix' => 'lt'])->assertSuccessful();

        expect(User::where('email', 'like', 'lt%@example.com')->count())->toBe(2)
            ->and(Tenant::count())->toBe(2)
            ->and(Company::withoutGlobalScopes()->count())->toBe(2);
    });

    it('is idempotent: running it again creates nothing new', function () {
        $this->artisan('loadtest:seed', ['--tenants' => 1, '--prefix' => 'again'])->assertSuccessful();
        $this->artisan('loadtest:seed', ['--tenants' => 1, '--prefix' => 'again'])->assertSuccessful();

        expect(User::where('email', 'again1@example.com')->count())->toBe(1)->and(Tenant::count())->toBe(1);
    });

    it('prints the credentials to use', function () {
        $this->artisan('loadtest:seed', ['--tenants' => 1, '--prefix' => 'cred'])->expectsOutputToContain('cred1..1@example.com / password');
    });

    it('refuses to run in production', function () {
        app()['env'] = 'production';

        $this->artisan('loadtest:seed')->expectsOutputToContain('Refusing')->assertFailed();
        expect(Tenant::count())->toBe(0);
    });
});

describe('loadtest:ledger', function () {
    it('posts journals, reports latency and verifies the ledger invariants', function () {
        $this->artisan('loadtest:ledger', ['--tenants' => 1, '--entries' => 5])
            ->expectsOutputToContain('Ledger invariants verified')
            ->assertSuccessful();

        expect(JournalEntry::withoutGlobalScopes()->where('status', 'posted')->count())->toBe(5);
    });

    it('refuses to run in production', function () {
        app()['env'] = 'production';

        $this->artisan('loadtest:ledger')->expectsOutputToContain('Refusing')->assertFailed();
    });
});
