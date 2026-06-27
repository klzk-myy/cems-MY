<?php

namespace App\Http\Requests;

class MergeCasesRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_case_id' => 'required|exists:compliance_cases,id',
        ];
    }
}
