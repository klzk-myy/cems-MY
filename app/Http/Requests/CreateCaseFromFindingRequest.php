<?php

namespace App\Http\Requests;

use App\Enums\ComplianceCaseType;
use Illuminate\Validation\Rule;

class CreateCaseFromFindingRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'case_type' => ['required', Rule::enum(ComplianceCaseType::class)],
            'summary' => ['nullable', 'string', 'max:1000'],

        ];
    }
}
