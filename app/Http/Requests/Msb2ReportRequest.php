<?php

namespace App\Http\Requests;

class Msb2ReportRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'nullable|date_format:Y-m-d',
        ];
    }
}
