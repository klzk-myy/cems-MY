<?php

namespace App\Http\Requests;

class MergeCaseRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'target_case_id' => 'required|exists:compliance_cases,id',
        ];
    }

    public function messages(): array
    {
        return [
            'target_case_id.required' => 'A target case must be selected for merging.',
            'target_case_id.exists' => 'The selected target case does not exist.',
        ];
    }
}
