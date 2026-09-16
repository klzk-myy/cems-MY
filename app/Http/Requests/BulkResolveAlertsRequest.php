<?php

namespace App\Http\Requests;

class BulkResolveAlertsRequest extends AuthorizedFormRequest
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
            'alert_ids' => 'required|array|min:1',
            'alert_ids.*' => 'integer|exists:alerts,id',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
