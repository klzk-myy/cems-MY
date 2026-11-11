<?php

namespace App\Http\Requests;

use App\Enums\ReportType;
use Cron\CronExpression;
use Illuminate\Validation\Rule;

class UpdateReportScheduleRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('parameters'))) {
            $decoded = json_decode((string) $this->input('parameters'), true);
            $this->merge([
                'parameters' => json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : $this->input('parameters'),
            ]);
        }

        $this->mergeRecipientList('notification_recipients');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'report_type' => ['sometimes', 'required', Rule::enum(ReportType::class)],
            'cron_expression' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! CronExpression::isValidExpression((string) $value)) {
                        $fail('The '.$attribute.' must be a valid cron expression.');
                    }
                },
            ],
            'parameters' => ['nullable', 'array'],
            'is_active' => ['boolean'],
            'notification_recipients' => ['nullable', 'array'],
            'notification_recipients.*' => ['email'],
        ];
    }
}
