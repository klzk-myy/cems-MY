<?php

namespace App\Http\Requests;

class TransactionWizardStep3Request extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->canCreateTransactions();
    }

    public function rules(): array
    {
        return [
            'wizard_session_id' => ['required', 'string'],
            'confirm_details' => ['required', 'accepted'],
            'idempotency_key' => ['required', 'string', 'unique:transactions,idempotency_key'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm_details.accepted' => 'You must confirm the transaction details',
            'idempotency_key.unique' => 'This transaction appears to be a duplicate',
        ];
    }
}
