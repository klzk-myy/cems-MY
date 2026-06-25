<?php

namespace App\Http\Requests;

use App\Enums\TransactionStatus;
use Illuminate\Contracts\Validation\ValidationRule;

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
            'status' => 'nullable|string|in:'.implode(',', array_map(fn ($case) => $case->value, TransactionStatus::cases())),
            'customer_id' => 'nullable|integer|exists:customers,id',
        ];
    }
}
