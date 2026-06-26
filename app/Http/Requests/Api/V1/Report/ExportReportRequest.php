<?php

namespace App\Http\Requests\Api\V1\Report;

use App\Enums\ReportType;
use App\Http\Requests\ApiFormRequest;

class ExportReportRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            // Only MSB2 is implemented in ReportController::export().
            // Expand this rule as additional export arms are added.
            'report_type' => 'required|in:'.ReportType::Msb2->value,
            'period' => 'required|string',
            'format' => 'required|in:CSV,PDF,XLSX',
        ];
    }
}
