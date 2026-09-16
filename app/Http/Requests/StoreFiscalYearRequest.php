<?php

namespace App\Http\Requests;

use App\Enums\Permission;

class StoreFiscalYearRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        // Creating a fiscal year generates its periods — a structural write
        // that needs manage_accounting, not just module access.
        $user = $this->user();

        return $user !== null && $user->role->canPerform(Permission::ManageAccounting);
    }

    public function rules(): array
    {
        return [
            'year_code' => 'required|string|max:10|unique:fiscal_years,year_code',
            'year' => 'nullable|integer|min:2000|max:2100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
        ];
    }
}
