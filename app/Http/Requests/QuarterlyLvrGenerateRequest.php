<?php

namespace App\Http\Requests;

class QuarterlyLvrGenerateRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'quarter' => 'required|date_format:Y-q',
        ];
    }
}
