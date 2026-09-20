<?php

namespace App\Http\Requests;

class ResolveAlertRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resolution' => 'required|string|max:5000',
        ];
    }
}
