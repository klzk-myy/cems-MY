<?php

namespace App\Http\Requests\Accounting;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class ManualMatchReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->role->canPerform(Permission::ManageAccounting);
    }

    public function rules(): array
    {
        return [
            'journal_entry_id' => ['required', 'integer', 'exists:journal_entries,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'journal_entry_id.required' => 'A journal entry ID is required for matching.',
            'journal_entry_id.exists' => 'The specified journal entry does not exist.',
        ];
    }
}
