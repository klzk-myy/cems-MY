<?php

namespace App\Http\Requests;

class QuarterlyLvrRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'quarter' => 'nullable|date_format:Y-q',
        ];
    }
}
