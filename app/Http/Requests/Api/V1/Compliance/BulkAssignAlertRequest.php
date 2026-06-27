<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\ApiFormRequest;

class BulkAssignAlertRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'alert_ids' => 'required|array|min:1',
            'alert_ids.*' => 'integer',
            'user_id' => 'required|integer|exists:users,id',
        ];
    }
}
