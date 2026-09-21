<?php

namespace App\Http\Requests;

use App\Enums\AlertPriority;
use App\Enums\UnifiedAlertStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UnifiedAlertIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source' => ['nullable', Rule::in(['all', 'alert', 'finding'])],
            'priority' => ['nullable', new Enum(AlertPriority::class)],
            'status' => ['nullable', new Enum(UnifiedAlertStatus::class)],
            'type' => ['nullable', 'string', 'max:100'],
            'customer' => ['nullable', 'string', 'max:255'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Normalize the priority filter so "Critical", "CRITICAL" and "critical"
     * are accepted interchangeably (enum values are stored lowercase).
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('priority')) {
            $this->merge(['priority' => strtolower((string) $this->input('priority'))]);
        }
    }
}
