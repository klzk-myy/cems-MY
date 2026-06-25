<?php

namespace App\Http\Requests;

class ReceiveStockTransferRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'items' => 'required|array',
            'items.*.id' => 'required|exists:stock_transfer_items,id',
            'items.*.quantity_received' => 'required|numeric|min:0',
        ];
    }
}
