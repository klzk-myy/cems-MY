<?php

namespace App\Http\Requests;

class RunReportRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'report_type' => 'required|in:msb2,lmca,qlvr,position_limit',
            'date' => 'nullable|date',
            'month' => 'nullable|date_format:Y-m',
            'quarter' => 'nullable|string',
        ];
    }
}
