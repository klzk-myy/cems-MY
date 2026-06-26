<?php

namespace App\Http\Requests;

use App\Enums\ReportType;

class RunReportRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'report_type' => 'required|in:'.ReportType::validationRule(),
            'date' => 'nullable|date',
            'month' => 'nullable|date_format:Y-m',
            'quarter' => 'nullable|string',
        ];
    }
}
