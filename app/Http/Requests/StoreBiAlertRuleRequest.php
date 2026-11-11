<?php

namespace App\Http\Requests;

use App\Models\Bi\BiAlertRule;
use Illuminate\Validation\Rule;

class StoreBiAlertRuleRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->mergeRecipientList('notification_recipients');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'metric' => ['required', Rule::in(array_keys(BiAlertRule::availableMetrics()))],
            'comparison' => ['required', Rule::in(array_keys(BiAlertRule::availableComparisons()))],
            'threshold' => ['required', 'numeric'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'level' => ['required', Rule::in(['warning', 'critical'])],
            'notification_recipients' => ['nullable', 'array'],
            'notification_recipients.*' => ['email'],
        ];
    }
}
