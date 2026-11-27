<?php

namespace Modules\Accounting\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Enums\CashFlowCategory;
use Modules\Tenancy\Infrastructure\TenantContext;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = app(TenantContext::class)->companyId();
        $account = $this->route('account');

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('accounts', 'code')->where('company_id', $companyId)->ignore($account)],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'parent_id' => ['nullable', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'is_postable' => ['sometimes', 'boolean'],
            'cash_flow_category' => ['required', Rule::enum(CashFlowCategory::class)],
        ];
    }
}
