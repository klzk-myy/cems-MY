<?php

namespace App\Http\Requests;

class StoreMsb2ReportRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'date' => 'required|date_format:Y-m-d',
        ];
    }
}
