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
        return [
            'period_id' => ['required', 'integer', 'exists:accounting_periods,id'],
            'closure_date' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
