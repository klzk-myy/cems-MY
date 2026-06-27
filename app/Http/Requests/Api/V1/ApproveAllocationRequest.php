<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\AuthorizedFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ApproveAllocationRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'approved_amount' => 'required|numeric|min:0.0001',
            'daily_limit_myr' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'approved_amount.required' => 'The approved amount is required.',
            'approved_amount.numeric' => 'The approved amount must be numeric.',
            'approved_amount.min' => 'The approved amount must be greater than zero.',
            'daily_limit_myr.numeric' => 'The daily limit must be numeric.',
            'daily_limit_myr.min' => 'The daily limit must be zero or greater.',
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
