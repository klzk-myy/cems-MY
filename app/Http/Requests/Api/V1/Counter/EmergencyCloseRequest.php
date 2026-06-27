<?php

namespace App\Http\Requests\Api\V1\Counter;

use App\Http\Requests\AuthorizedFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class EmergencyCloseRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reason for emergency closure is required.',
            'reason.string' => 'The reason must be a string.',
            'reason.max' => 'The reason may not be greater than 500 characters.',
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
