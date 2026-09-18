<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Enums\AlertPriority;
use App\Enums\FlagStatus;
use App\Http\Requests\ApiFormRequest;

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
            'priority' => 'nullable|in:'.implode(',', array_column(AlertPriority::cases(), 'value')),
            'assigned' => 'nullable|in:yes,no',
            'status' => 'nullable|in:'.implode(',', array_column(FlagStatus::cases(), 'value')),
        ];
    }
}
