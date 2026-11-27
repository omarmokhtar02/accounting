<?php

namespace Modules\Platform\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Platform\Application\UpdatePlatformSettings;
use Modules\Shared\Application\PlatformSettings;
use Modules\Subscriptions\Domain\Models\Plan;

class SettingsController extends Controller
{
    public function edit(PlatformSettings $settings): View
    {
        return view('platform::settings.edit', [
            'values' => $settings->effective(),
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, UpdatePlatformSettings $update): RedirectResponse
    {
        $validated = $request->validate([
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'trial_plan_code' => ['required', Rule::exists('plans', 'code')],
            'subscription_readonly_grace_days' => ['required', 'integer', 'min:0', 'max:365'],
            'billing_instructions_en' => ['nullable', 'string', 'max:1000'],
            'billing_instructions_ar' => ['nullable', 'string', 'max:1000'],
            'billing_contact_email' => ['nullable', 'email', 'max:255'],
        ]);

        $update->handle($validated);

        return back()->with('status', __('platform.settings_saved'));
    }
}
