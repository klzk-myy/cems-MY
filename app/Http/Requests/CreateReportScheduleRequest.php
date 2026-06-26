<?php

namespace App\Http\Requests;

use App\Enums\ReportType;

class CreateReportScheduleRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'report_type' => 'required|in:'.$this->reportTypeValues(),
            'cron_expression' => 'required|string',
            'parameters' => 'nullable|array',
            'notification_recipients' => 'nullable|array',
        ];
    }

    protected function reportTypeValues(): string
    {
        return implode(',', array_map(fn (ReportType $type) => $type->value, ReportType::cases()));
    }
}
