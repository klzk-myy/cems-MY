<?php

namespace App\Http\Requests\Api\V1\Counter;

use App\Http\Requests\AuthorizedFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CounterReconciliationRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'date' => 'required|date_format:Y-m-d',
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'A date is required.',
            'date.date_format' => 'The date must be in YYYY-MM-DD format.',
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
