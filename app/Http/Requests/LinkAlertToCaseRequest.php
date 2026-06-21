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

    public function messages(): array
    {
        return [
            'alert_id.required' => 'An alert must be selected.',
            'alert_id.exists' => 'The selected alert does not exist.',
        ];
    }
}
