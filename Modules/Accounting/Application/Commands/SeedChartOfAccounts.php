<?php

namespace Modules\Accounting\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Exceptions\ChartAlreadyExists;
use Modules\Accounting\Domain\Models\Account;
use Modules\Accounting\Domain\Models\AccountMapping;
use Modules\Accounting\Domain\Services\ChartOfAccountsValidator;
use Modules\Accounting\Domain\ValueObjects\AccountSeed;
use Modules\Shared\Application\RecordAudit;
use Modules\Tenancy\Infrastructure\TenantContext;

final class SeedChartOfAccounts
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ChartOfAccountsValidator $validator,
        private readonly RecordAudit $audit,
    ) {}

    /**
     * Creates every account of the template for the active company and maps the roles, in one
     * transaction. Only allowed on a company that has no accounts yet, so a pack can never overwrite
     * a chart that already carries ledger history.
     *
     * @param  list<AccountSeed>  $seeds
     * @param  list<AccountRole>  $requiredRoles
     * @return int number of accounts created
     */
    public function handle(array $seeds, array $requiredRoles = []): int
    {
        $this->validator->assertValid($seeds, $requiredRoles);

        return DB::transaction(function () use ($seeds): int {
            $companyId = $this->context->companyId();

            if (Account::query()->where('company_id', $companyId)->exists()) {
                throw ChartAlreadyExists::forCompany();
            }

            $ids = [];

            foreach ($this->parentsFirst($seeds) as $seed) {
                $account = Account::create([
                    'company_id' => $companyId,
                    'parent_id' => $seed->parentCode === null ? null : $ids[$seed->parentCode],
                    'code' => $seed->code,
                    'name' => $seed->nameEn,
                    'name_ar' => $seed->nameAr,
                    'type' => $seed->type,
                    'is_postable' => $seed->postable,
                    'cash_flow_category' => $seed->cashFlow,
                ]);

                $ids[$seed->code] = $account->id;

                if ($seed->role !== null) {
                    AccountMapping::create(['company_id' => $companyId, 'role' => $seed->role, 'account_id' => $account->id]);
                }
            }

            $this->audit->handle('company', $companyId, 'chart_seeded', null, ['accounts' => count($seeds)]);

            return count($seeds);
        });
    }

    /**
     * @param  list<AccountSeed>  $seeds
     * @return list<AccountSeed>
     */
    private function parentsFirst(array $seeds): array
    {
        $byCode = [];
        foreach ($seeds as $seed) {
            $byCode[$seed->code] = $seed;
        }

        $depth = function (AccountSeed $seed) use ($byCode): int {
            $d = 0;
            for ($c = $seed; $c->parentCode !== null; $c = $byCode[$c->parentCode]) {
                $d++;
            }

            return $d;
        };

        usort($seeds, fn (AccountSeed $a, AccountSeed $b) => $depth($a) <=> $depth($b));

        return $seeds;
    }
}
