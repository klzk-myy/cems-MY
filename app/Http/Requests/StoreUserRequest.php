<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasUserValidationRules;
use App\Models\User;
use App\Rules\PasswordRules;

class StoreUserRequest extends AuthorizedFormRequest
{
    use HasUserValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return array_merge($this->userValidationRules(), [
            'password' => PasswordRules::forNew(),
            'password_confirmation' => 'required',
        ]);
    }
}
