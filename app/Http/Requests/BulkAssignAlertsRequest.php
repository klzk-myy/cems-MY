<?php

namespace App\Http\Requests;

class BulkAssignAlertsRequest extends AuthorizedFormRequest
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
            'user_id' => 'required|integer|exists:users,id',
        ];
    }
}
