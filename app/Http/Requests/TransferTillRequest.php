<?php

namespace App\Http\Requests;

class TransferTillRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'direction' => ['required', 'in:load,unload'],

        ];
    }
}
