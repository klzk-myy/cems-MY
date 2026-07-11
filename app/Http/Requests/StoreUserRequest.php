<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasUserValidationRules;
use App\Models\User;

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
                'min:12',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
            ],
            'password_confirmation' => 'required',
        ]);
    }
}
