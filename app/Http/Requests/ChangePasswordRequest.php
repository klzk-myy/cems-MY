<?php

namespace App\Http\Requests;

use App\Rules\PasswordRules;

class ChangePasswordRequest extends AuthorizedFormRequest
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
            'current_password' => ['required'],
            'password' => array_merge(
                ['different:current_password'],
                PasswordRules::forChange($this->user())
            ),

        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.different' => 'The new password must be different from the current password.',
        ];
    }
}
