<?php

namespace App\Http\Requests;

use App\Enums\AllocationDirection;
use Illuminate\Validation\Rules\Enum;

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
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'direction' => ['required', new Enum(AllocationDirection::class)],

        ];
    }
}
