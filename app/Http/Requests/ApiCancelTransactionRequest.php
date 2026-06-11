<?php

namespace App\Http\Requests;

class ApiCancelTransactionRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:10|max:1000',
        ];
    }
}
