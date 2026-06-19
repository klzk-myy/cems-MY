<?php

namespace App\Http\Requests\Api\V1\Eod;

use App\Http\Requests\ApiFormRequest;

class GenerateReportRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'date' => 'required|date_format:Y-m-d',
            'branch_id' => 'nullable|exists:branches,id',
            'counter_id' => 'nullable|exists:counters,id',
            'format' => 'nullable|in:pdf,json',
        ];
    }
}
