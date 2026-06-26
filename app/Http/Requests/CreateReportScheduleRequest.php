<?php

namespace App\Http\Requests;

use App\Enums\ReportType;

class CreateReportScheduleRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'report_type' => 'required|in:'.ReportType::validationRule(),
            'cron_expression' => 'required|string',
            'parameters' => 'nullable|array',
            'notification_recipients' => 'nullable|array',
        ];
    }
}
