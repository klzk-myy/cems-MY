<?php

namespace App\Http\Requests\Api\V1\Rate;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateRateRequest extends ApiFormRequest
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
