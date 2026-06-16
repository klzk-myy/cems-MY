<?php

namespace App\Http\Requests\Api\V1\Rate;

use App\Http\Requests\AuthorizedFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateRateRequest extends AuthorizedFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'currencies' => 'required|array|min:1',
            'currencies.*' => 'string|size:3',
        ];
    }
}
