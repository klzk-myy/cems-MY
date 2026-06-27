<?php

namespace App\Http\Requests\Mfa;

use App\Http\Requests\AuthorizedFormRequest;

class SetupMfaRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|digits:6',
        ];
    }
}
