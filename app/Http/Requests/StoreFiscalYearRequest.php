<?php

namespace App\Http\Requests;

class StoreFiscalYearRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'year_code' => 'required|string|max:10|unique:fiscal_years,year_code',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ];
    }
}
