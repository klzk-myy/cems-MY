<?php

namespace App\Http\Requests\Accounting;

use App\Enums\Permission;
use App\Http\Requests\AuthorizedFormRequest;

class ClosePeriodRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->role->canPerform(Permission::ManageAccounting);
    }

    public function rules(): array
    {
        // The period comes from the route model binding; the service
        // closes it at now() and persists no reason field.
        return [];
    }
}
