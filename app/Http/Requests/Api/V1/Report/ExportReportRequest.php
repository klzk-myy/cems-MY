<?php

namespace App\Http\Requests\Api\V1\Report;

use App\Enums\ReportType;
use App\Http\Requests\ApiFormRequest;

class ExportReportRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'report_type' => 'required|in:'.$this->reportTypeValues(),
            'period' => 'required|string',
            'format' => 'required|in:CSV,PDF,XLSX',
        ];
    }

    protected function reportTypeValues(): string
    {
        return implode(',', array_map(fn (ReportType $type) => $type->value, ReportType::cases()));
    }
}
