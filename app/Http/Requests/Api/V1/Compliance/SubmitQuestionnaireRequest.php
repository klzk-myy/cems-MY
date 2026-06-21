<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\AuthorizedFormRequest;

class SubmitQuestionnaireRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'responses' => 'required|array',
            'responses.*' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'responses.required' => 'Questionnaire responses are required.',
            'responses.array' => 'Responses must be an array.',
            'responses.*.string' => 'Each response must be a string.',
        ];
    }
}
