<?php

namespace Modules\Tenancy\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActiveCompanyController extends Controller
{
    /**
     * Switches the active company. The id only counts if the user is a member of that company:
     * membership is re-checked here, never trusted from the form.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(['company_id' => ['required', 'integer']]);

        $company = $request->user()->companies()->whereKey($validated['company_id'])->firstOrFail();

        $request->session()->put('company_id', $company->id);

        return back()->with('status', __('app.company_switched'));
    }
}
