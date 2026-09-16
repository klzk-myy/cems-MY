<?php

namespace App\Http\Requests;

use App\Enums\CaseNoteType;
use Illuminate\Validation\Rules\Enum as EnumRule;

class AddCaseNoteRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note_type' => ['required', new EnumRule(CaseNoteType::class)],
            'content' => 'required|string|max:2000',
            'is_internal' => 'boolean',
        ];
    }
}
