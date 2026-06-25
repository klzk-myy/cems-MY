<?php

namespace App\Http\Requests;

class ApproveCancelRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => 'nullable|string|max:500',
        ];
    }
}
