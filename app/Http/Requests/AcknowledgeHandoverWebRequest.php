<?php

namespace App\Http\Requests;

class AcknowledgeHandoverWebRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'verified' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
