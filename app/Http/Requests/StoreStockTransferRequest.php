<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockTransferRequest extends FormRequest
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
            'type' => 'required|in:Standard,Emergency,Scheduled,Return',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.currency_code' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.rate' => 'required|numeric|min:0',
            'items.*.value_myr' => 'required|numeric|min:0',
        ];
    }
}
