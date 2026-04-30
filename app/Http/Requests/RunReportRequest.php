<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RunReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'report_type' => 'required|in:msb2,lctr,lmca,qlvr,position_limit',
            'date' => 'nullable|date',
            'month' => 'nullable|date_format:Y-m',
            'quarter' => 'nullable|string',
        ];
    }
}
