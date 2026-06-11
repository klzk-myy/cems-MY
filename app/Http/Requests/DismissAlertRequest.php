<?php

namespace App\Http\Requests;

class DismissAlertRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => 'nullable|string|max:500',
        ];
    }
}
