<?php

namespace App\Http\Requests\Api\V1\Mfa;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ValidatesCurrentPassword;

class DisableMfaRequest extends ApiFormRequest
{
    use ValidatesCurrentPassword;

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
            'current_password' => $this->currentPasswordRules(),
            'code' => 'required|digits:6',
        ];
    }
}
