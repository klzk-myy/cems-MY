<?php

namespace App\Http\Requests;

class UpdateReportScheduleRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'cron_expression' => 'nullable|string',
            'parameters' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'notification_recipients' => 'nullable|array',
        ];
    }
}
