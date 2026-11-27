<?php

namespace Modules\Identity\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Identity\Application\SyncRolePermissions;
use Modules\Identity\Application\TenantRoleProvisioner;
use Modules\Tenancy\Infrastructure\TenantContext;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(TenantContext $context): View
    {
        $roles = Role::query()->where('tenant_id', $context->tenantId())->withCount('permissions')->orderBy('name')->get();

        return view('identity::roles.index', ['roles' => $roles]);
    }

    public function edit(Role $role, TenantContext $context): View
    {
        abort_unless($role->tenant_id === $context->tenantId(), 404);

        return view('identity::roles.edit', [
            'role' => $role,
            'groups' => TenantRoleProvisioner::grouped(),
            'granted' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, Role $role, SyncRolePermissions $sync, TenantContext $context): RedirectResponse
    {
        abort_unless($role->tenant_id === $context->tenantId(), 404);

        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $sync->handle($role, $validated['permissions'] ?? []);

        return redirect()->route('admin.roles.index')->with('status', __('admin.role_updated'));
    }
}
