<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\AuthorizedFormRequest;

class ScreeningRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'customer_ids' => 'required|array|min:1|max:100',
            'customer_ids.*' => 'integer|exists:customers,id',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_ids.required' => 'Customer IDs are required.',
            'customer_ids.array' => 'Customer IDs must be an array.',
            'customer_ids.min' => 'At least one customer ID is required.',
            'customer_ids.max' => 'A maximum of 100 customer IDs is allowed.',
            'customer_ids.*.integer' => 'Each customer ID must be an integer.',
            'customer_ids.*.exists' => 'One or more customer IDs do not exist.',
        ];
    }
}
