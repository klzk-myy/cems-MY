<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Enums\AlertPriority;
use App\Enums\FlagStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class AlertIndexRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => 'nullable|integer|min:1|max:100',
            'priority' => ['nullable', Rule::enum(AlertPriority::class)],
            'assigned' => 'nullable|in:yes,no',
            'status' => ['nullable', Rule::enum(FlagStatus::class)],
        ];
    }
}
