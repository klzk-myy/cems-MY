<?php

namespace App\Http\Requests;

use App\Enums\Permission;

class StoreMsb2ReportRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->role->canPerform(Permission::ViewReports);
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date_format:Y-m-d',
        ];
    }
}
