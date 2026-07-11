<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasUserValidationRules;

class UpdateUserRequest extends AuthorizedFormRequest
{
    use HasUserValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        return array_merge($this->userValidationRules(isUpdate: true), [
            'is_active' => 'required|boolean',
        ]);
    }
}
