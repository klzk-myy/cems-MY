<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RunRevaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        return $user && $user->role->canPerformRevaluation();
    }

    public function rules(): array
    {
        return [];
    }
}
