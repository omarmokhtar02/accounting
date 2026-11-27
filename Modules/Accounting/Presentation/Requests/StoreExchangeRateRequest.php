<?php

namespace Modules\Accounting\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExchangeRateRequest extends FormRequest
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
        return [
            'currency_code' => ['required', 'string', Rule::exists('currencies', 'code')],
            'rate_date' => ['required', 'date_format:Y-m-d'],
            'rate' => ['required', 'regex:/^\d{1,10}(\.\d{1,10})?$/', 'not_in:0,0.0'],
        ];
    }
}
