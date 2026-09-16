<?php

namespace App\Http\Requests;

class UpdateCurrencyRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The currency code is immutable once created (it is the primary key and
     * is referenced by transactions, positions, rates and journal lines);
     * only descriptive attributes may change here.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:4'],
            'rate_unit' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'rate_inverse' => ['required', 'boolean'],
        ];
    }
}
