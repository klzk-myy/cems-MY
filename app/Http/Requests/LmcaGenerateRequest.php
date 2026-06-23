<?php

namespace App\Http\Requests;

class LmcaGenerateRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'month' => 'required|date_format:Y-m',
        ];
    }
}
