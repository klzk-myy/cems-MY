<?php

namespace App\Http\Requests;

class SubmitAllocationRequest extends AuthorizedFormRequest
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
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code', 'distinct'],
            'lines.*.amount' => ['required', 'numeric', 'min:0.0001'],
            'counter_id' => ['nullable', 'integer', 'exists:counters,id'],

        ];
    }
}
