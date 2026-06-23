<?php

namespace App\Http\Requests;

class LoginRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'username' => 'required|string',
            'password' => 'required',
        ];
    }
}
