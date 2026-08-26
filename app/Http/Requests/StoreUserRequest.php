<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasUserValidationRules;
use App\Models\User;
use App\Rules\PasswordComplexityRule;

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
            'password' => [
                'required',
                'string',
                'confirmed',
                new PasswordComplexityRule,
            ],
            'password_confirmation' => 'required',
        ]);
    }
}
