<?php

namespace App\Http\Requests;

class RejectCancelRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:10|max:500',
        ];
    }
}
