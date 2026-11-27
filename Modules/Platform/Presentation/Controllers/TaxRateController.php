<?php

namespace Modules\Platform\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Localization\Application\CountryRegistry;
use Modules\Platform\Application\SaveTaxRate;
use Modules\Platform\Application\TaxRateOverview;
use Modules\Tax\Domain\Exceptions\InvalidTaxRateChange;
use Modules\Tax\Domain\Models\TaxRateSchedule;

class TaxRateController extends Controller
{
    public function index(TaxRateOverview $overview): View
    {
        return view('platform::tax.index', ['overview' => $overview->build()]);
    }

    public function store(Request $request, SaveTaxRate $save, CountryRegistry $countries): RedirectResponse
    {
        $validated = $request->validate([
            'country' => ['required', Rule::in($countries->codes())],
            'code' => ['required', 'string', 'max:40'],
            'percent' => ['required', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'effective_from' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $save->schedule($validated['country'], $validated['code'], $validated['percent'], CarbonImmutable::parse($validated['effective_from']),
                $validated['note'] ?? null, $request->user('admin')->id);
        } catch (InvalidTaxRateChange $e) {
            return back()->withInput()->withErrors(['rate' => $e->userMessage()]);
        }

        return back()->with('status', __('tax.rate_scheduled'));
    }

    public function destroy(Request $request, TaxRateSchedule $schedule, SaveTaxRate $save): RedirectResponse
    {
        try {
            $save->remove($schedule, $request->user('admin')->id);
        } catch (InvalidTaxRateChange $e) {
            return back()->withErrors(['rate' => $e->userMessage()]);
        }

        return back()->with('status', __('tax.rate_removed'));
    }
}
