<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class FinalizeBranchClosingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->role->value === UserRole::Admin->value
                || $user->role->value === UserRole::Manager->value);
    }

    public function rules(): array
    {
        // Finalization derives everything from the route's {branch} binding,
        // the active workflow, and the authenticated user — these required
        // fields were never submitted by the form nor used by the service,
        // so the action always failed validation.
        return [];
    }
}
