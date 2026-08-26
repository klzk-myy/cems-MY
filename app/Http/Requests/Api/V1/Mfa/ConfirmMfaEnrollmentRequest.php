<?php

namespace App\Http\Requests\Api\V1\Mfa;

use App\Http\Requests\ApiFormRequest;

class ConfirmMfaEnrollmentRequest extends ApiFormRequest
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
            'code' => 'required|digits:6',
        ];
    }
}
