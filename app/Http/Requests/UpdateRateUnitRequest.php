<?php

namespace App\Http\Requests;

class UpdateRateUnitRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'currency_code' => 'required|string|exists:currencies,code',
            'rate_unit' => 'required|integer|min:1|max:1000000000',
            'rate_inverse' => 'required|boolean',
            'rate_buy' => 'nullable|numeric|min:0.0001|required_with:rate_sell',
            'rate_sell' => 'nullable|numeric|min:0.0001|required_with:rate_buy',
            'reason' => 'nullable|string|max:500',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ];
    }

    public function messages(): array
    {
        return [
            'currency_code.required' => 'The currency code is required.',
            'currency_code.exists' => 'The selected currency does not exist.',
            'rate_unit.required' => 'The quote unit is required.',
            'rate_unit.integer' => 'The quote unit must be a whole number.',
            'rate_unit.min' => 'The quote unit must be at least 1.',
            'rate_unit.max' => 'The quote unit is too large.',
            'rate_inverse.required' => 'The quote direction is required.',
            'rate_inverse.boolean' => 'The quote direction is invalid.',
            'rate_buy.numeric' => 'The buy rate must be a number.',
            'rate_buy.min' => 'The buy rate must be greater than zero.',
            'rate_buy.required_with' => 'The buy rate is required when a sell rate is given.',
            'rate_sell.numeric' => 'The sell rate must be a number.',
            'rate_sell.min' => 'The sell rate must be greater than zero.',
            'rate_sell.required_with' => 'The sell rate is required when a buy rate is given.',
            'reason.max' => 'The reason may not exceed 500 characters.',
            'branch_id.exists' => 'The selected branch does not exist.',
        ];
    }
}
