<?php

namespace App\Http\Requests;

class StoreCustomerNoteRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => 'required|string|max:2000',
        ];
    }
}
