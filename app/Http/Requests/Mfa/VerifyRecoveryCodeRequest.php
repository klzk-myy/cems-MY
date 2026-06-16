<?php

namespace App\Http\Requests\Mfa;

use App\Http\Requests\AuthorizedFormRequest;

class VerifyRecoveryCodeRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'recovery_code' => 'required|string|min:6|max:50',
            'password' => 'required|string',
        ];
    }
}
