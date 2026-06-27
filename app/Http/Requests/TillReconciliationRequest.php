<?php

namespace App\Http\Requests;

class TillReconciliationRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'nullable|date',
            'till_id' => 'required|string',
        ];
    }
}
