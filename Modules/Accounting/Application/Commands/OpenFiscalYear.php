<?php

namespace Modules\Accounting\Application\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Domain\Models\FiscalYear;
use Modules\Shared\Application\RecordAudit;
use Modules\Tenancy\Infrastructure\TenantContext;

final class OpenFiscalYear
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly RecordAudit $audit,
    ) {}

    /**
     * Creates the fiscal year with one open period per calendar month between the two dates.
     */
    public function handle(string $name, CarbonImmutable $startsOn, CarbonImmutable $endsOn): FiscalYear
    {
        return DB::transaction(function () use ($name, $startsOn, $endsOn): FiscalYear {
            $companyId = $this->context->companyId();

            $year = FiscalYear::create([
                'company_id' => $companyId,
                'name' => $name,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
            ]);

            $cursor = $startsOn;
            $number = 1;

            while ($cursor <= $endsOn) {
                $end = $cursor->endOfMonth()->startOfDay();
                if ($end > $endsOn) {
                    $end = $endsOn;
                }

                $year->periods()->create([
                    'company_id' => $companyId,
                    'number' => $number++,
                    'starts_on' => $cursor,
                    'ends_on' => $end,
                ]);

                $cursor = $end->addDay();
            }

            $this->audit->handle('fiscal_year', $year->id, 'opened', null, ['name' => $name]);

            return $year;
        });
    }
}
