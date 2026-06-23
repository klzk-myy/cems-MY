<?php

namespace App\Http\Requests;

class LmcaReportRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'month' => 'nullable|date_format:Y-m',
        ];
    }
}
