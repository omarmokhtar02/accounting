<?php

namespace Modules\Tax\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Tax\Application\TaxRateResolver;
use Modules\Tax\Domain\Exceptions\TaxRateNotConfigured;
use Modules\Tax\Domain\Models\TaxCode;
use Modules\Tenancy\Infrastructure\TenantContext;

class TaxCodeController extends Controller
{
    /**
     * The company's tax codes with the rate in force today and the next scheduled change (read-only:
     * rates are managed by the platform, per country).
     */
    public function index(TenantContext $context, TaxRateResolver $resolver): View
    {
        $company = $context->company();

        $rows = TaxCode::query()->where('company_id', $company->id)->orderBy('kind')->orderBy('code')->get()->map(function (TaxCode $code) use ($company, $resolver) {
            try {
                $current = $resolver->rateFor($company->country_code, $code->code, now());
            } catch (TaxRateNotConfigured) {
                $current = null;
            }

            return ['code' => $code, 'current' => $current, 'next' => $resolver->nextChange($company->country_code, $code->code, now())];
        });

        return view('tax::codes.index', ['rows' => $rows]);
    }
}
