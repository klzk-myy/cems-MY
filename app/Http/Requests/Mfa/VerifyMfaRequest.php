<?php

namespace App\Http\Requests\Mfa;

use App\Http\Requests\AuthorizedFormRequest;

/**
 * Validates an MFA verification code.
 *
 * The code field accepts either a 6-digit TOTP code or a 10-character recovery code.
 */
class VerifyMfaRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'code' => 'required|string|min:6|max:10',
        ];
    }
}
