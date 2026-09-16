<?php

namespace App\Http\Requests;

use App\Enums\Permission;

class FiscalYearCloseRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->role->canPerform(Permission::ManageAccounting);
    }

    public function rules(): array
    {
        return [
            'confirm_code' => 'required|string',
        ];
    }
}
