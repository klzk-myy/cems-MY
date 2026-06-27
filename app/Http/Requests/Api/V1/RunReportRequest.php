<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ReportType;
use App\Http\Requests\AuthorizedFormRequest;

class RunReportRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'report_type' => 'required|in:'.ReportType::validationRule(),
            'period' => 'required|string',
            'format' => 'required|in:CSV,PDF,XLSX',
        ];
    }

    public function messages(): array
    {
        return [
            'report_type.required' => 'Report type is required.',
            'report_type.in' => 'Invalid report type. Must be '.ReportType::validationRule().'.',
            'period.required' => 'Period is required.',
            'period.string' => 'Period must be a string.',
            'format.required' => 'Export format is required.',
            'format.in' => 'Invalid format. Must be CSV, PDF, or XLSX.',
        ];
    }
}
