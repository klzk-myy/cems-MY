<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\ApiFormRequest;

class BulkResolveAlertRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'alert_ids' => 'required|array|min:1',
            'alert_ids.*' => 'integer',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
