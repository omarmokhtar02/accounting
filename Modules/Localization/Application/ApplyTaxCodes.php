<?php

namespace Modules\Localization\Application;

use Modules\Tax\Domain\Models\TaxCode;
use Modules\Tax\Domain\ValueObjects\TaxCodeSeed;
use Modules\Tenancy\Infrastructure\TenantContext;

final class ApplyTaxCodes
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * Gives the active company the tax codes of its country pack. Idempotent: codes that already exist
     * are left alone.
     *
     * @param  list<TaxCodeSeed>  $seeds
     * @return int number of codes created
     */
    public function handle(array $seeds): int
    {
        $companyId = $this->context->companyId();
        $created = 0;

        foreach ($seeds as $seed) {
            $code = TaxCode::query()->firstOrCreate(
                ['company_id' => $companyId, 'code' => $seed->code],
                ['kind' => $seed->kind, 'name_en' => $seed->nameEn, 'name_ar' => $seed->nameAr, 'is_active' => true],
            );

            $created += $code->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }
}
