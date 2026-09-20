<?php

namespace App\Http\Requests;

use App\Models\StockTransfer;
use Illuminate\Validation\Rule;

class StoreStockTransferRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_branch_name' => 'required|string',
            'destination_branch_name' => 'required|string|different:source_branch_name',
            'type' => ['required', Rule::in([StockTransfer::TYPE_STANDARD, StockTransfer::TYPE_EMERGENCY, StockTransfer::TYPE_SCHEDULED, StockTransfer::TYPE_RETURN])],
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.currency_code' => 'required|string|exists:currencies,code',
            'items.*.quantity' => 'required|numeric|min:0',
            // Display hints only — the service re-derives both server-side
            // from the source position's cost basis (never request input).
            'items.*.rate' => 'nullable|numeric|min:0',
            'items.*.value_myr' => 'nullable|numeric|min:0',
        ];
    }
}
