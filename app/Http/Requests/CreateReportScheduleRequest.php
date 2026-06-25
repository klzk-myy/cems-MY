<?php

namespace App\Http\Requests;

class CreateReportScheduleRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'report_type' => 'required|in:msb2,lmca,qlvr,position_limit',
            'cron_expression' => 'required|string',
            'parameters' => 'nullable|array',
            'notification_recipients' => 'nullable|array',
        ];
    }
}
