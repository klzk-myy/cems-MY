<?php

namespace App\Http\Requests;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Transaction::class);
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date', 'before_or_equal:date_to'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'type' => ['nullable', Rule::enum(TransactionType::class)],
            'status' => ['nullable', Rule::enum(TransactionStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.before_or_equal' => 'The start date must be before or equal to the end date.',
            'date_to.after_or_equal' => 'The end date must be after or equal to the start date.',
        ];
    }
}
