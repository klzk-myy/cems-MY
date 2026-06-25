<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\AuthorizedFormRequest;

class RunReportRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'report_type' => 'required|in:msb2,trial_balance,pl,balance_sheet',
            'period' => 'required|string',
            'format' => 'required|in:CSV,PDF,XLSX',
        ];
    }

    public function messages(): array
    {
        return [
            'report_type.required' => 'Report type is required.',
            'report_type.in' => 'Invalid report type. Must be msb2, trial_balance, pl, or balance_sheet.',
            'period.required' => 'Period is required.',
            'period.string' => 'Period must be a string.',
            'format.required' => 'Export format is required.',
            'format.in' => 'Invalid format. Must be CSV, PDF, or XLSX.',
        ];
    }
}
