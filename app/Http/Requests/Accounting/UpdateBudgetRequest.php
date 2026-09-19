<?php

namespace App\Http\Requests\Accounting;

use App\Enums\Permission;
use App\Http\Requests\AuthorizedFormRequest;

/**
 * Validates a budget amount update.
 */
class UpdateBudgetRequest extends AuthorizedFormRequest
{
    /**
     * Budget mutation requires manage_accounting — the module's write-level
     * permission. access_accounting only opens read surfaces.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->role->canPerform(Permission::ManageAccounting);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'budget_myr' => 'required|numeric|min:0',
        ];
    }
}
