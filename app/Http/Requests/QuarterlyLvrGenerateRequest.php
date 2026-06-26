<?php

namespace App\Http\Requests;

class QuarterlyLvrGenerateRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'quarter' => ['required', 'regex:/^\d{4}-Q[1-4]$/'],
        ];
    }
}
