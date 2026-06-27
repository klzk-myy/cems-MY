<?php

namespace App\Http\Requests;

class CopyPreviousRateRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'nullable|date|before_or_equal:today',
        ];
    }
}
