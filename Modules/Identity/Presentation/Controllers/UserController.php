<?php

namespace Modules\Identity\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Modules\Identity\Application\CreateTenantUser;
use Modules\Subscriptions\Domain\Exceptions\PlanLimitReached;
use Modules\Tenancy\Domain\Models\Company;
use Modules\Tenancy\Infrastructure\TenantContext;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(TenantContext $context): View
    {
        $users = User::query()->where('tenant_id', $context->tenantId())->with(['roles', 'companies'])->orderBy('name')->get();

        return view('identity::users.index', ['users' => $users]);
    }

    public function create(TenantContext $context): View
    {
        return view('identity::users.create', [
            'roles' => $this->assignableRoles($context),
            'companies' => Company::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, CreateTenantUser $createTenantUser, TenantContext $context): RedirectResponse
    {
        $roleNames = $this->assignableRoles($context)->pluck('name')->all();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::in($roleNames)],
            'company_ids' => ['required', 'array', 'min:1'],
            'company_ids.*' => ['integer', Rule::exists('companies', 'id')->where('tenant_id', $context->tenantId())],
        ]);

        try {
            $createTenantUser->handle(
                $validated['name'],
                $validated['email'],
                Hash::make($validated['password']),
                $validated['role'],
                array_map('intval', $validated['company_ids']),
            );
        } catch (PlanLimitReached $e) {
            return back()->withInput()->withErrors(['plan' => $e->userMessage()]);
        }

        return redirect()->route('admin.users.index')->with('status', __('admin.user_created'));
    }

    /**
     * The owner role is never assignable from the UI.
     *
     * @return Collection<int, Role>
     */
    private function assignableRoles(TenantContext $context)
    {
        return Role::query()->where('tenant_id', $context->tenantId())->where('name', '!=', 'owner')->orderBy('name')->get();
    }
}
