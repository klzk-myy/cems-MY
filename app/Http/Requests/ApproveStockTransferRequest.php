<?php

namespace App\Http\Requests;

class ApproveStockTransferRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'notes' => 'nullable|string|max:500',
        ];
    }
}
