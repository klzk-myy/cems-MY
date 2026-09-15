<?php

namespace App\Http\Requests;

class LoginRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => 'required|string|max:255',
            'password' => 'required|string',
        ];
    }
}
