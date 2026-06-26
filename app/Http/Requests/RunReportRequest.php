<?php

namespace App\Http\Requests;

use App\Enums\ReportType;

class RunReportRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'report_type' => 'required|in:'.$this->reportTypeValues(),
            'date' => 'nullable|date',
            'month' => 'nullable|date_format:Y-m',
            'quarter' => 'nullable|string',
        ];
    }

    protected function reportTypeValues(): string
    {
        return implode(',', array_map(fn (ReportType $type) => $type->value, ReportType::cases()));
    }
}
