<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\PasswordRules;

class ResetPasswordRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('resetPassword', $this->route('user'));
    }

    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'password' => PasswordRules::forChange($user),
        ];
    }
}
