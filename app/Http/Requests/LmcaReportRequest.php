<?php

namespace App\Http\Requests;

use App\Enums\Permission;

class LmcaReportRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->role->canPerform(Permission::ViewReports);
    }

    public function rules(): array
    {
        return [
            'month' => 'nullable|date_format:Y-m',
        ];
    }
}
