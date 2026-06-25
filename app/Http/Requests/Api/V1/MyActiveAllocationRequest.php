<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\AuthorizedFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class MyActiveAllocationRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'currency_code' => 'required|string|size:3',
        ];
    }

    public function messages(): array
    {
        return [
            'currency_code.required' => 'The currency code is required.',
            'currency_code.string' => 'The currency code must be a string.',
            'currency_code.size' => 'The currency code must be exactly 3 characters.',
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
