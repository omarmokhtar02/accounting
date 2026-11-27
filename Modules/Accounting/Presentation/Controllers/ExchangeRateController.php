<?php

namespace Modules\Accounting\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Accounting\Application\Commands\SaveExchangeRate;
use Modules\Accounting\Domain\Exceptions\DomainException;
use Modules\Accounting\Domain\Models\ExchangeRateEntry;
use Modules\Accounting\Presentation\Requests\StoreExchangeRateRequest;
use Modules\Shared\Domain\Contracts\CurrencyRegistry;
use Modules\Tenancy\Infrastructure\TenantContext;

class ExchangeRateController extends Controller
{
    public function index(TenantContext $context, CurrencyRegistry $currencies): View
    {
        $base = $context->company()->base_currency;
        $codes = array_values(array_diff(array_keys($currencies->all()), [$base]));

        return view('accounting::exchange-rates.index', [
            'rates' => ExchangeRateEntry::query()
                ->where('company_id', $context->companyId())
                ->orderByDesc('rate_date')->orderBy('currency_code')
                ->paginate(30),
            'currencies' => array_combine($codes, $codes),
            'baseCurrency' => $base,
        ]);
    }

    public function store(StoreExchangeRateRequest $request, SaveExchangeRate $save): RedirectResponse
    {
        try {
            $save->handle($request->validated('currency_code'), CarbonImmutable::parse($request->validated('rate_date')), $request->validated('rate'));
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['currency_code' => $e->userMessage()]);
        }

        return redirect()->route('accounting.exchange-rates.index')->with('status', __('accounting.fx_saved'));
    }
}
