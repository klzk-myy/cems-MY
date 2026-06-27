<?php

namespace App\Http\Requests;

class AddCaseLinkRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'linked_type' => 'required|string',
            'linked_id' => 'required|integer',
        ];
    }
}
