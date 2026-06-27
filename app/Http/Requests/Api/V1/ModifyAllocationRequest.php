<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\AuthorizedFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ModifyAllocationRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_amount' => 'required|numeric|min:0.0001',
            'is_increase' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'new_amount.required' => 'The new amount is required.',
            'new_amount.numeric' => 'The new amount must be numeric.',
            'new_amount.min' => 'The new amount must be greater than zero.',
            'is_increase.required' => 'The increase/decrease indicator is required.',
            'is_increase.boolean' => 'The increase/decrease indicator must be a boolean.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422));
    }
}
