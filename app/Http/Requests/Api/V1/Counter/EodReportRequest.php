<?php

namespace App\Http\Requests\Api\V1\Counter;

use App\Http\Requests\AuthorizedFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class EodReportRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'date' => 'required|date_format:Y-m-d',
            'branch_id' => 'nullable|exists:branches,id',
            'counter_id' => 'nullable|exists:counters,id',
            'format' => 'nullable|in:pdf,json',
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'A date is required.',
            'date.date_format' => 'The date must be in YYYY-MM-DD format.',
            'branch_id.exists' => 'The selected branch does not exist.',
            'counter_id.exists' => 'The selected counter does not exist.',
            'format.in' => 'The format must be either pdf or json.',
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
