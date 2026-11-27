<?php

namespace Modules\Accounting\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Accounting\Application\Commands\ChangeAccountStatus;
use Modules\Accounting\Application\Commands\UpdateAccount;
use Modules\Accounting\Application\DTOs\UpdateAccountData;
use Modules\Accounting\Domain\Enums\AccountStatus;
use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Enums\CashFlowCategory;
use Modules\Accounting\Domain\Exceptions\DomainException;
use Modules\Accounting\Domain\Models\Account;
use Modules\Accounting\Presentation\Requests\StoreAccountRequest;
use Modules\Accounting\Presentation\Requests\UpdateAccountRequest;
use Modules\Shared\Application\RecordAudit;
use Modules\Tenancy\Infrastructure\TenantContext;

class AccountController extends Controller
{
    public function index(TenantContext $context): View
    {
        $accounts = Account::query()
            ->where('company_id', $context->companyId())
            ->orderBy('code')
            ->paginate(50);

        return view('accounting::accounts.index', ['accounts' => $accounts]);
    }

    public function create(TenantContext $context): View
    {
        return view('accounting::accounts.create', [
            'types' => AccountType::cases(),
            'categories' => CashFlowCategory::cases(),
            'parents' => Account::query()->where('company_id', $context->companyId())->orderBy('code')->get(),
        ]);
    }

    public function store(StoreAccountRequest $request, TenantContext $context, RecordAudit $audit): RedirectResponse
    {
        $account = Account::create([
            ...$request->safe()->only(['code', 'name', 'name_ar', 'type', 'parent_id']),
            'company_id' => $context->companyId(),
            'is_postable' => $request->boolean('is_postable', true),
            'cash_flow_category' => $request->input('cash_flow_category', CashFlowCategory::None->value),
        ]);

        $audit->handle('account', $account->id, 'created', null, ['code' => $account->code]);

        return redirect()->route('accounting.accounts.index')->with('status', __('accounting.account_created'));
    }

    public function edit(Account $account, TenantContext $context): View
    {
        abort_unless($account->company_id === $context->companyId(), 404);

        return view('accounting::accounts.edit', [
            'account' => $account,
            'types' => AccountType::cases(),
            'categories' => CashFlowCategory::cases(),
            'parents' => Account::query()->where('company_id', $context->companyId())->whereKeyNot($account->id)->orderBy('code')->get(),
        ]);
    }

    public function update(UpdateAccountRequest $request, Account $account, UpdateAccount $updateAccount, TenantContext $context): RedirectResponse
    {
        abort_unless($account->company_id === $context->companyId(), 404);

        try {
            $updateAccount->handle($account, new UpdateAccountData(
                $request->validated('code'),
                $request->validated('name'),
                $request->validated('name_ar'),
                AccountType::from($request->validated('type')),
                $request->validated('parent_id') ? (int) $request->validated('parent_id') : null,
                $request->boolean('is_postable', true),
                CashFlowCategory::from($request->validated('cash_flow_category')),
            ));
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['account' => $e->userMessage()]);
        }

        return redirect()->route('accounting.accounts.index')->with('status', __('accounting.account_updated'));
    }

    public function changeStatus(Request $request, Account $account, ChangeAccountStatus $changeAccountStatus, TenantContext $context): RedirectResponse
    {
        abort_unless($account->company_id === $context->companyId(), 404);

        $validated = $request->validate(['status' => ['required', Rule::enum(AccountStatus::class)]]);

        try {
            $changeAccountStatus->handle($account, AccountStatus::from($validated['status']));
        } catch (DomainException $e) {
            return back()->withErrors(['account' => $e->userMessage()]);
        }

        return back()->with('status', __('accounting.account_status_changed'));
    }
}
