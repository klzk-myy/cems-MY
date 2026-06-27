<?php

namespace App\Http\Requests;

class StoreStrRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'alert_id' => 'nullable|exists:flagged_transactions,id',
            'transaction_ids' => 'required|array',
            'transaction_ids.*' => 'exists:transactions,id',
            'reason' => 'required|string|min:20',
        ];
    }
}
