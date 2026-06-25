<?php

namespace App\Http\Requests;

class LinkAlertToCaseRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'alert_id' => 'required|exists:alerts,id',
        ];
    }
}
