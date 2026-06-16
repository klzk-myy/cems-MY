<?php

namespace App\Http\Requests\Mfa;

use App\Http\Requests\AuthorizedFormRequest;

class DisableMfaRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'code' => 'required|digits:6',
        ];
    }
}
