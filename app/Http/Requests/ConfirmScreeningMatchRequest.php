<?php

namespace App\Http\Requests;

class ConfirmScreeningMatchRequest extends AuthorizedFormRequest
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
            'reason' => 'required|string|max:1000',

        ];
    }
}
