<?php

namespace App\Http\Requests;

class DismissFindingRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
        ];
    }
}
