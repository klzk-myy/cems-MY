<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\AuthorizedFormRequest;

class BulkAssignAlertRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'alert_ids' => 'required|array|min:1',
            'alert_ids.*' => 'integer',
            'user_id' => 'required|integer|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'alert_ids.required' => 'At least one alert must be selected.',
            'alert_ids.min' => 'At least one alert must be selected.',
            'alert_ids.*.integer' => 'Each alert ID must be an integer.',
            'user_id.required' => 'A user must be assigned.',
            'user_id.integer' => 'User ID must be an integer.',
            'user_id.exists' => 'The selected user does not exist.',
        ];
    }
}
