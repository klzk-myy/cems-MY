<?php

namespace App\Http\Requests\Accounting;

use App\Enums\JournalEntryStatus;
use App\Http\Requests\AuthorizedFormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the journal entry index filter request.
 */
class JournalIndexRequest extends AuthorizedFormRequest
{
    /**
     * Authorization is enforced by the controller via the JournalEntry policy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(JournalEntryStatus::class)],
            'date' => ['nullable', 'date'],
        ];
    }
}
