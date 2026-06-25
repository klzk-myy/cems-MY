<?php

namespace App\Http\Requests;

class TillReportRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'till_id' => 'required|string',
            'date' => 'nullable|date',
        ];
    }
}
