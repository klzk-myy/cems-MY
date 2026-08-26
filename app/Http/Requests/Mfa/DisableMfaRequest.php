<?php

namespace App\Http\Requests\Mfa;

use App\Http\Requests\AuthorizedFormRequest;
use App\Http\Requests\Concerns\ValidatesCurrentPassword;

class DisableMfaRequest extends AuthorizedFormRequest
{
    use ValidatesCurrentPassword;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRules(),
            'code' => 'required|digits:6',
        ];
    }
}
