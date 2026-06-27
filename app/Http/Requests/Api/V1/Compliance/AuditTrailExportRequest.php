<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\ApiFormRequest;

class AuditTrailExportRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'case_id' => 'nullable|integer',
        ];
    }
}
