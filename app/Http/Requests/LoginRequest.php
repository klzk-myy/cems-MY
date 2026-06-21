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

    public function messages(): array
    {
        return [
            'username.required' => 'Username is required.',
            'password.required' => 'Password is required.',
        ];
    }
}
