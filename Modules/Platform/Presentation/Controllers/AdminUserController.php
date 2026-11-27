<?php

namespace Modules\Platform\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Platform\Application\SaveAdminUser;
use Modules\Platform\Domain\Exceptions\AdminOperationDenied;
use Modules\Platform\Domain\Models\AdminRole;
use Modules\Platform\Domain\Models\AdminUser;

class AdminUserController extends Controller
{
    public function index(): View
    {
        return view('platform::admins.index', ['admins' => AdminUser::query()->with('role')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('platform::admins.form', ['admin' => new AdminUser(['is_active' => true, 'locale' => 'en']), 'roles' => AdminRole::orderBy('name_en')->get()]);
    }

    public function store(Request $request, SaveAdminUser $save): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('admin_users', 'email')],
            'password' => ['required', 'string'],
            'admin_role_id' => ['required', Rule::exists('admin_roles', 'id')],
            'locale' => ['required', Rule::in(['en', 'ar'])],
        ]);

        try {
            $save->create($validated['name'], $validated['email'], $validated['password'], AdminRole::findOrFail($validated['admin_role_id']), $validated['locale']);
        } catch (AdminOperationDenied $e) {
            return back()->withInput($request->except('password'))->withErrors(['password' => $e->userMessage()]);
        }

        return redirect()->route('platform.admins.index')->with('status', __('platform.admin_saved'));
    }

    public function edit(AdminUser $admin): View
    {
        return view('platform::admins.form', ['admin' => $admin, 'roles' => AdminRole::orderBy('name_en')->get()]);
    }

    public function update(Request $request, AdminUser $admin, SaveAdminUser $save): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string'],
            'admin_role_id' => ['required', Rule::exists('admin_roles', 'id')],
            'locale' => ['required', Rule::in(['en', 'ar'])],
        ]);

        try {
            $save->update($admin, $validated['name'], AdminRole::findOrFail($validated['admin_role_id']), $request->boolean('is_active'),
                $validated['password'] ?? null, $validated['locale'], $request->user('admin')->id);
        } catch (AdminOperationDenied $e) {
            return back()->withInput($request->except('password'))->withErrors(['admin' => $e->userMessage()]);
        }

        return redirect()->route('platform.admins.index')->with('status', __('platform.admin_saved'));
    }
}
