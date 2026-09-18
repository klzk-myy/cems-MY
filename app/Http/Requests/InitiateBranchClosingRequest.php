<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class InitiateBranchClosingRequest extends FormRequest
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
        // The workflow is initiated from the route's {branch} binding only —
        // reason/scheduled_date were never stored on the model, so requiring
        // them made the initiate button permanently invalid.
        return [];
    }
}
