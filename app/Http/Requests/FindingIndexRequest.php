<?php

namespace App\Http\Requests;

use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Enums\FindingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FindingIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', Rule::enum(FindingStatus::class)],
            'severity' => ['nullable', Rule::enum(FindingSeverity::class)],
            'type' => ['nullable', Rule::enum(FindingType::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
