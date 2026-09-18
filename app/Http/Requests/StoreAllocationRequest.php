<?php

namespace App\Http\Requests;

class StoreAllocationRequest extends AuthorizedFormRequest
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
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code', 'distinct'],
            'lines.*.amount' => ['required', 'numeric', 'min:0.0001'],
            'daily_limit_myr' => ['nullable', 'numeric', 'min:0'],

        ];
    }
}
