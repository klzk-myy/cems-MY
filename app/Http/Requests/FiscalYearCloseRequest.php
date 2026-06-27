<?php

namespace App\Http\Requests;

class FiscalYearCloseRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirm_code' => 'required|string',
        ];
    }
}
