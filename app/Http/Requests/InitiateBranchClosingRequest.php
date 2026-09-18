<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class InitiateBranchClosingRequest extends FormRequest
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
        // The workflow is initiated from the route's {branch} binding only —
        // reason/scheduled_date were never stored on the model, so requiring
        // them made the initiate button permanently invalid.
        return [];
    }
}
