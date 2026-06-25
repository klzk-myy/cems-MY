<?php

namespace App\Http\Requests;

class ResetPasswordRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'password' => 'required|string|min:12|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
        ];
    }
}
