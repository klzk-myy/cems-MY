<?php

namespace App\Http\Requests;

class QuarterlyLvrRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quarter' => ['nullable', 'regex:/^\d{4}-Q[1-4]$/'],
        ];
    }
}
