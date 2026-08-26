<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\PasswordComplexityRule;
use App\Rules\PasswordNotRecentlyUsed;

class ResetPasswordRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'password' => [
                'required',
                'string',
                'confirmed',
                new PasswordComplexityRule,
                new PasswordNotRecentlyUsed($user),
            ],
        ];
    }
}
