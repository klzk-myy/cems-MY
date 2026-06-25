<?php

namespace App\Http\Requests;

class UpdateReportStatusRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'date' => 'required_with:date|date_format:Y-m-d',
            'month' => 'required_with:month|date_format:Y-m',
            'status' => 'required|in:Submitted',
        ];
    }
}
