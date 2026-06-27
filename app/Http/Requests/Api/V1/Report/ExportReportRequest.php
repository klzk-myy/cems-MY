<?php

namespace App\Http\Requests\Api\V1\Report;

use App\Enums\ReportType;
use App\Http\Requests\ApiFormRequest;

class ExportReportRequest extends ApiFormRequest
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
}
