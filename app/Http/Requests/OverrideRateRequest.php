<?php

namespace App\Http\Requests;

class OverrideRateRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rate_buy' => 'required|numeric|min:0.0001',
            'rate_sell' => 'required|numeric|min:0.0001',
            'reason' => 'nullable|string|max:500',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'effective_date' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'rate_buy.required' => 'The buy rate is required.',
            'rate_buy.numeric' => 'The buy rate must be a number.',
            'rate_buy.min' => 'The buy rate must be greater than zero.',
            'rate_sell.required' => 'The sell rate is required.',
            'rate_sell.numeric' => 'The sell rate must be a number.',
            'rate_sell.min' => 'The sell rate must be greater than zero.',
            'reason.max' => 'The reason may not exceed 500 characters.',
            'branch_id.exists' => 'The selected branch does not exist.',
            'effective_date.date' => 'The effective date must be a valid date.',
        ];
    }
}
