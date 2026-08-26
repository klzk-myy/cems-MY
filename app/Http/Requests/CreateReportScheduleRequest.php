<?php

namespace App\Http\Requests;

use App\Enums\ReportType;
use Cron\CronExpression;

class CreateReportScheduleRequest extends AuthorizedFormRequest
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

        if (is_string($this->input('notification_recipients'))) {
            $emails = array_values(array_filter(array_map(
                'trim',
                preg_split('/[\n,]+/', (string) $this->input('notification_recipients')) ?: []
            )));
            $this->merge(['notification_recipients' => $emails]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'report_type' => ['required', 'in:'.implode(',', array_column(ReportType::cases(), 'value'))],
            'cron_expression' => [
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
