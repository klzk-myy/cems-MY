<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateReportScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'report_type' => 'required|in:msb2,lctr,lmca,qlvr,position_limit',
            'cron_expression' => 'required|string',
            'parameters' => 'nullable|array',
            'notification_recipients' => 'nullable|array',
        ];
    }
}
