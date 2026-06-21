<?php

namespace App\Http\Requests\Api\V1\Counter;

use App\Http\Requests\AuthorizedFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class InitiateOpeningRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'requested_floats' => 'required|array',
            'requested_floats.*' => 'required|numeric|min:0.0001',
        ];
    }

    public function messages(): array
    {
        return [
            'requested_floats.required' => 'Requested floats are required.',
            'requested_floats.array' => 'Requested floats must be an array.',
            'requested_floats.*.required' => 'Each requested float amount is required.',
            'requested_floats.*.numeric' => 'Each requested float amount must be numeric.',
            'requested_floats.*.min' => 'Each requested float amount must be greater than zero.',
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
