<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreCurrencyRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
                Rule::unique('currencies', 'code')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:4'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.size' => 'The currency code must be exactly 3 characters.',
            'code.regex' => 'The currency code must be an uppercase ISO alpha-3 code (e.g. USD).',
            'code.unique' => 'An active currency with this code already exists.',
        ];
    }
}
