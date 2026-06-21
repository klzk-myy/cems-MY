<?php

namespace App\Http\Requests;

class AddCaseLinkRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'linked_type' => 'required|string',
            'linked_id' => 'required|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'linked_type.required' => 'A link type must be specified.',
            'linked_id.required' => 'A linked entity ID must be provided.',
            'linked_id.integer' => 'The linked entity ID must be a valid integer.',
        ];
    }
}
