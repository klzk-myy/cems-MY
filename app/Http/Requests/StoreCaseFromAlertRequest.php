<?php

namespace App\Http\Requests;

class StoreCaseFromAlertRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'alert_ids' => 'required|array|min:1',
            'alert_ids.*' => 'exists:alerts,id',
        ];
    }

    public function messages(): array
    {
        return [
            'alert_ids.required' => 'At least one alert must be selected to create a case.',
            'alert_ids.min' => 'At least one alert must be selected to create a case.',
            'alert_ids.*.exists' => 'One or more selected alerts are invalid.',
        ];
    }
}
