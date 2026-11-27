<?php

namespace Modules\Platform\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Platform\Application\SaveAdminRole;
use Modules\Platform\Domain\Exceptions\AdminOperationDenied;
use Modules\Platform\Domain\Models\AdminPermission;
use Modules\Platform\Domain\Models\AdminRole;

class AdminRoleController extends Controller
{
    public function index(): View
    {
        return view('platform::roles.index', ['roles' => AdminRole::query()->withCount(['permissions', 'users'])->orderBy('id')->get()]);
    }

    public function create(): View
    {
        return view('platform::roles.form', ['role' => new AdminRole, 'groups' => $this->groups(), 'granted' => []]);
    }

    public function store(Request $request, SaveAdminRole $save): RedirectResponse
    {
        $validated = $this->validated($request);

        $save->create($validated['name_en'], $validated['name_ar'], $validated['permissions'] ?? []);

        return redirect()->route('platform.roles.index')->with('status', __('platform.role_saved'));
    }

    public function edit(AdminRole $role): View
    {
        return view('platform::roles.form', ['role' => $role, 'groups' => $this->groups(), 'granted' => $role->permissions()->pluck('key')->all()]);
    }

    public function update(Request $request, AdminRole $role, SaveAdminRole $save): RedirectResponse
    {
        $validated = $this->validated($request);

        try {
            $save->update($role, $validated['name_en'], $validated['name_ar'], $validated['permissions'] ?? []);
        } catch (AdminOperationDenied $e) {
            return back()->withErrors(['role' => $e->userMessage()]);
        }

        return redirect()->route('platform.roles.index')->with('status', __('platform.role_saved'));
    }

    public function destroy(AdminRole $role, SaveAdminRole $save): RedirectResponse
    {
        try {
            $save->delete($role);
        } catch (AdminOperationDenied $e) {
            return back()->withErrors(['role' => $e->userMessage()]);
        }

        return redirect()->route('platform.roles.index')->with('status', __('platform.role_deleted'));
    }

    /** @return array<string, list<string>> */
    private function groups(): array
    {
        return AdminPermission::query()->orderBy('module')->orderBy('key')->get()->groupBy('module')->map->pluck('key')->map->all()->all();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);
    }
}
