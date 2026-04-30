<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStrRequest extends FormRequest
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
