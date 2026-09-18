<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\PasswordRules;

class PasswordResetRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => 'required',
            'email' => 'required|email',
            'password' => PasswordRules::forChange(
                User::where('email', (string) $this->input('email'))->first()
            ),

        ];
    }
}
