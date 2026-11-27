<?php

namespace Modules\Accounting\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Shared\Domain\Contracts\CurrencyRegistry;
use Modules\Tenancy\Infrastructure\TenantContext;

class StoreJournalEntryRequest extends FormRequest
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
        $context = app(TenantContext::class);
        $companyId = $context->companyId();
        $currencies = app(CurrencyRegistry::class);
        $base = $context->company()->base_currency;
        $lineRules = [];

        // Each line is checked against its own currency: a KWD line takes 3 decimals even in an EGP company.
        foreach ((array) $this->input('lines', []) as $i => $line) {
            $code = is_array($line) ? (string) ($line['currency'] ?? '') : '';
            $decimals = $currencies->exists($code) ? $currencies->get($code)->decimals : $currencies->get($base)->decimals;
            $fraction = $decimals === 0 ? '' : '(\.\d{1,'.$decimals.'})?';
            $amount = ['nullable', 'regex:/^\d{1,13}'.$fraction.'$/'];
            $lineRules['lines.'.$i.'.debit'] = $amount;
            $lineRules['lines.'.$i.'.credit'] = $amount;
        }

        return [
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'lines.*.currency' => ['nullable', Rule::exists('currencies', 'code')],
            'lines.*.rate' => ['nullable', 'regex:/^\d{1,10}(\.\d{1,10})?$/'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ] + $lineRules;
    }
}
