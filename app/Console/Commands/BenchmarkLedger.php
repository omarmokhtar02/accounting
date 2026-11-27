<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Accounting\Application\Commands\CreateJournalEntry;
use Modules\Accounting\Application\Commands\OpenFiscalYear;
use Modules\Accounting\Application\Commands\PostJournalEntry;
use Modules\Accounting\Application\DTOs\CreateJournalEntryData;
use Modules\Accounting\Application\DTOs\JournalLineData;
use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Models\Account;
use Modules\Accounting\Domain\Models\JournalEntry;
use Modules\Accounting\Domain\Models\JournalLine;
use Modules\Identity\Application\RegisterTenant;
use Modules\Tenancy\Infrastructure\TenantContext;
use Spatie\Permission\PermissionRegistrar;

/**
 * In-process write-path benchmark: create + post journals across several tenants, report latency
 * percentiles and throughput, then verify the ledger invariants. It measures application and
 * database cost on ONE connection; it is not a concurrency test (use loadtest:seed + the HTTP tools).
 */
class BenchmarkLedger extends Command
{
    protected $signature = 'loadtest:ledger {--tenants=3} {--entries=200 : Journals per tenant}';

    protected $description = 'Benchmark journal create+post throughput and verify ledger invariants (never in production)';

    public function handle(RegisterTenant $registerTenant, TenantContext $context): int
    {
        if (app()->isProduction()) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }

        $tenants = (int) $this->option('tenants');
        $entries = (int) $this->option('entries');
        $run = substr(bin2hex(random_bytes(4)), 0, 8);
        $hash = Hash::make('password');
        $latencies = [];
        $posted = 0;
        $started = hrtime(true);

        for ($t = 1; $t <= $tenants; $t++) {
            $user = $registerTenant->handle("Bench {$t}", "bench{$run}{$t}@example.com", $hash, "Bench Co {$t}", 'EG', 'en');
            $context->setTenant($user->tenant);
            $context->setCompany($user->companies()->first());
            app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
            Auth::login($user);

            app(OpenFiscalYear::class)->handle('2026', CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));
            $cash = Account::create(['company_id' => $context->companyId(), 'code' => '1000', 'name' => 'Cash', 'type' => AccountType::Asset]);
            $sales = Account::create(['company_id' => $context->companyId(), 'code' => '4000', 'name' => 'Sales', 'type' => AccountType::Revenue]);

            for ($i = 1; $i <= $entries; $i++) {
                $amount = random_int(100, 500000);
                $date = CarbonImmutable::parse('2026-01-01')->addDays($i % 360);

                $t0 = hrtime(true);
                $entry = app(CreateJournalEntry::class)->handle(new CreateJournalEntryData(
                    $date, "Bench sale {$i}",
                    [new JournalLineData($cash->id, $amount, 0), new JournalLineData($sales->id, 0, $amount)],
                    "bench-{$run}-{$t}-{$i}",
                ));
                app(PostJournalEntry::class)->handle($entry, $user, allowSelfPosting: true);
                $latencies[] = (hrtime(true) - $t0) / 1e6;
                $posted++;
            }

            $this->verify($context, $entries);
            Auth::logout();
            $context->clear();
        }

        $elapsed = (hrtime(true) - $started) / 1e9;
        sort($latencies);
        $pct = fn (float $p) => $latencies[(int) min(count($latencies) - 1, floor($p * count($latencies)))];

        $this->table(['Metric', 'Value'], [
            ['Driver', DB::getDriverName()],
            ['Tenants x entries', "{$tenants} x {$entries}"],
            ['Journals created+posted', $posted],
            ['Wall time', sprintf('%.2f s (includes tenant setup)', $elapsed)],
            ['Throughput', sprintf('%.1f journals/s', $posted / $elapsed)],
            ['Latency p50', sprintf('%.1f ms', $pct(0.50))],
            ['Latency p95', sprintf('%.1f ms', $pct(0.95))],
            ['Latency p99', sprintf('%.1f ms', $pct(0.99))],
            ['Latency max', sprintf('%.1f ms', end($latencies))],
        ]);
        $this->info('Ledger invariants verified for every tenant (debits = credits, unique gap-free numbering).');

        return self::SUCCESS;
    }

    private function verify(TenantContext $context, int $expected): void
    {
        $companyId = $context->companyId();
        $debits = (int) JournalLine::where('company_id', $companyId)->sum('debit');
        $credits = (int) JournalLine::where('company_id', $companyId)->sum('credit');
        $numbers = JournalEntry::where('company_id', $companyId)->pluck('number');

        throw_if($debits !== $credits, \RuntimeException::class, "Trial balance broken: {$debits} != {$credits}");
        throw_if($numbers->count() !== $expected || $numbers->unique()->count() !== $expected, \RuntimeException::class, 'Numbering has gaps or duplicates');
    }
}
