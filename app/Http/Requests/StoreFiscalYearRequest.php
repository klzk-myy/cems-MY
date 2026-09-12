<?php

namespace App\Http\Requests;

class StoreFiscalYearRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year_code' => 'required|string|max:10|unique:fiscal_years,year_code',
            'year' => 'nullable|integer|min:2000|max:2100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
        ];
    }
}
