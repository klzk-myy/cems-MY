<?php

namespace App\Http\Requests;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Transaction Index Request
 *
 * Validates query parameters for the transaction listing endpoint.
 */
class IndexTransactionRequest extends AuthorizedFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:100',
            'status' => ['nullable', 'string', Rule::enum(TransactionStatus::class)],
            'type' => ['nullable', 'string', Rule::enum(TransactionType::class)],
            'currency_code' => 'nullable|string|size:3',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'is_refund' => 'nullable|boolean',
            'customer_id' => 'nullable|integer|exists:customers,id',
        ];
    }
}
