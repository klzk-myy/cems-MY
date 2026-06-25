<?php

namespace App\Http\Requests\Api\V1\Report;

use App\Http\Requests\ApiFormRequest;

class ExportReportRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'report_type' => 'required|in:msb2,trial_balance,pl,balance_sheet',
            'period' => 'required|string',
            'format' => 'required|in:CSV,PDF,XLSX',
        ];
    }
}
