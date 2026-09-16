<?php

namespace App\Http\Requests\Accounting;

use App\Enums\Permission;
use App\Http\Requests\AuthorizedFormRequest;

class MarkReconciliationExceptionRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->role->canPerform(Permission::ManageAccounting);
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
        ];
    }
}
