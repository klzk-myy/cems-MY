<?php

namespace App\Http\Requests;

class CancelStockTransferRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
        ];
    }
}
