<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class BiExportRequest extends AuthorizedFormRequest
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
            'type' => ['required', Rule::in(['digest', 'branches', 'currencies', 'tellers', 'customers'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ];
    }
}
