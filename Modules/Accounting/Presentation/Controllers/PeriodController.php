<?php

namespace Modules\Accounting\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Application\Commands\ClosePeriod;
use Modules\Accounting\Domain\Exceptions\DomainException;
use Modules\Accounting\Domain\Models\AccountingPeriod;
use Modules\Tenancy\Infrastructure\TenantContext;

class PeriodController extends Controller
{
    public function close(Request $request, AccountingPeriod $period, ClosePeriod $closePeriod, TenantContext $context): RedirectResponse
    {
        abort_unless($period->company_id === $context->companyId(), 404);

        try {
            $closePeriod->handle($period, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['period' => $e->userMessage()]);
        }

        return back()->with('status', __('accounting.period_closed_ok'));
    }
}
