<?php

namespace Modules\Accounting\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Accounting\Application\Commands\MapAccountRole;
use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Exceptions\DomainException;
use Modules\Accounting\Domain\Models\Account;
use Modules\Accounting\Domain\Models\AccountMapping;
use Modules\Tenancy\Infrastructure\TenantContext;

class AccountRoleController extends Controller
{
    public function index(TenantContext $context): View
    {
        $companyId = $context->companyId();

        return view('accounting::account-roles.index', [
            'roles' => AccountRole::cases(),
            'mapped' => AccountMapping::query()->where('company_id', $companyId)->pluck('account_id', 'role')->all(),
            'accounts' => Account::query()->where('company_id', $companyId)->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, MapAccountRole $mapAccountRole, TenantContext $context): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::enum(AccountRole::class)],
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('company_id', $context->companyId())],
        ]);

        try {
            $mapAccountRole->handle(AccountRole::from($validated['role']), Account::query()->findOrFail($validated['account_id']));
        } catch (DomainException $e) {
            return back()->withErrors(['role' => $e->userMessage()]);
        }

        return back()->with('status', __('accounting.role_mapped'));
    }
}
