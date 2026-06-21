<?php

namespace App\Http\Requests\Api\V1\Counter;

use App\Http\Requests\AuthorizedFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ApproveAndOpenRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'teller_id' => 'required|integer|exists:users,id',
            'approved_floats' => 'required|array',
            'approved_floats.*' => 'required|numeric|min:0',
            'daily_limits' => 'nullable|array',
            'daily_limits.*' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'teller_id.required' => 'Teller ID is required.',
            'teller_id.integer' => 'Teller ID must be an integer.',
            'teller_id.exists' => 'The selected teller does not exist.',
            'approved_floats.required' => 'Approved floats are required.',
            'approved_floats.array' => 'Approved floats must be an array.',
            'approved_floats.*.required' => 'Each approved float amount is required.',
            'approved_floats.*.numeric' => 'Each approved float amount must be numeric.',
            'daily_limits.array' => 'Daily limits must be an array.',
            'daily_limits.*.numeric' => 'Each daily limit must be numeric.',
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
