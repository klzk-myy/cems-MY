<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class FinalizeBranchClosingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        // Matrix check, matching the route's role:manage_branch_closing
        // middleware — an identity check here would override the matrix so
        // a granted role could never reach the action.
        return $user !== null && $user->role->canPerform(Permission::ManageBranchClosing);
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
