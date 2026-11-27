<?php

namespace Modules\Localization\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Localization\Application\CountryRegistry;
use Modules\Localization\Application\CreateCompany;
use Modules\Subscriptions\Domain\Exceptions\PlanLimitReached;
use Modules\Tenancy\Domain\Models\Company;

class CompanyController extends Controller
{
    public function index(Request $request, CountryRegistry $countries): View
    {
        return view('localization::companies.index', [
            'companies' => $request->user()->companies()->orderBy('name')->get(),
            'countries' => $countries->all(),
        ]);
    }

    public function create(CountryRegistry $countries): View
    {
        return view('localization::companies.create', ['countries' => $countries->all()]);
    }

    public function store(Request $request, CreateCompany $createCompany, CountryRegistry $countries): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'country_code' => ['required', Rule::in($countries->codes())],
        ]);

        try {
            $company = $createCompany->handle($validated['name'], $validated['name_ar'] ?? null, $validated['country_code']);
        } catch (PlanLimitReached $e) {
            return back()->withInput()->withErrors(['plan' => $e->userMessage()]);
        }

        $this->grantAccess($request, $company);

        return redirect()->route('localization.companies.index')->with('status', __('app.company_created'));
    }

    private function grantAccess(Request $request, Company $company): void
    {
        $company->users()->syncWithoutDetaching([$request->user()->id]);
    }
}
