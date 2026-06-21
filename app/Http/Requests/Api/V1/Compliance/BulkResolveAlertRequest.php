<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\AuthorizedFormRequest;

class BulkResolveAlertRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'alert_ids' => 'required|array|min:1',
            'alert_ids.*' => 'integer',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'alert_ids.required' => 'At least one alert must be selected.',
            'alert_ids.min' => 'At least one alert must be selected.',
            'alert_ids.*.integer' => 'Each alert ID must be an integer.',
            'notes.max' => 'Notes must not exceed 1000 characters.',
        ];
    }
}
