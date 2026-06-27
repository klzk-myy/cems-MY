<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\AuthorizedFormRequest;

class AddNoteCaseRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note_type' => 'required|string',
            'content' => 'required|string|max:2000',
            'is_internal' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'note_type.required' => 'Note type is required.',
            'note_type.string' => 'Note type must be a string.',
            'content.required' => 'Note content is required.',
            'content.max' => 'Note content must not exceed 2000 characters.',
        ];
    }
}
