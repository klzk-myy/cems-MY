<?php

namespace App\Http\Requests;

class StoreMsb2ReportRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date_format:Y-m-d',
        ];
    }
}
