<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\AuthorizedFormRequest;

class ClosePeriodRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
