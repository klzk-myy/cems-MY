<?php

namespace App\Http\Requests\Api\V1\Counter;

use App\Http\Requests\AuthorizedFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class HandoverCounterRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verified' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'verified.required' => 'Verification status is required.',
            'verified.boolean' => 'Verification status must be a boolean.',
            'notes.max' => 'Notes may not be greater than 500 characters.',
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
