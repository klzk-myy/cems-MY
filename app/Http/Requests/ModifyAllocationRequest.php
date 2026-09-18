<?php

namespace App\Http\Requests;

class ModifyAllocationRequest extends AuthorizedFormRequest
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
            'amount' => ['required', 'numeric', 'min:0.0001'],
            'direction' => ['required', 'in:increase,decrease'],

        ];
    }
}
