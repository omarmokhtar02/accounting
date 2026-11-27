<?php

namespace Modules\Accounting\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Accounting\Application\Commands\OpenFiscalYear;
use Modules\Accounting\Domain\Models\FiscalYear;
use Modules\Accounting\Presentation\Requests\StoreFiscalYearRequest;
use Modules\Tenancy\Infrastructure\TenantContext;

class FiscalYearController extends Controller
{
    public function index(TenantContext $context): View
    {
        $years = FiscalYear::query()
            ->where('company_id', $context->companyId())
            ->with('periods')
            ->orderByDesc('starts_on')
            ->get();

        return view('accounting::fiscal-years.index', ['years' => $years]);
    }

    public function create(): View
    {
        return view('accounting::fiscal-years.create');
    }

    public function store(StoreFiscalYearRequest $request, OpenFiscalYear $openFiscalYear): RedirectResponse
    {
        $openFiscalYear->handle(
            $request->string('name')->toString(),
            CarbonImmutable::parse($request->input('starts_on')),
            CarbonImmutable::parse($request->input('ends_on')),
        );

        return redirect()->route('accounting.fiscal-years.index')->with('status', __('accounting.fiscal_year_created'));
    }
}
