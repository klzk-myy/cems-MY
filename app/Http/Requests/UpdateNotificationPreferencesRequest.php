<?php

namespace App\Http\Requests;

class UpdateNotificationPreferencesRequest extends AuthorizedFormRequest
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
            'types' => ['nullable', 'array'],
            'types.*' => ['string'],
            'digest_enabled' => ['nullable', 'boolean'],

        ];
    }
}
