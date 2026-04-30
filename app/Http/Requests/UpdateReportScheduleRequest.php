<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReportScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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
