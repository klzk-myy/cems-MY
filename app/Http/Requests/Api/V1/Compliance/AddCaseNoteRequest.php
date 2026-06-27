<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\ApiFormRequest;

class AddCaseNoteRequest extends ApiFormRequest
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
}
