<?php

namespace App\Http\Requests;

class CreateCaseFromAlertsRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'alert_ids' => 'required|array|min:1',
            'alert_ids.*' => 'exists:alerts,id',
        ];
    }
}
