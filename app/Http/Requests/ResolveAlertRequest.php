<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResolveAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resolution' => 'required|string|max:5000',
            'resolution_type' => 'required|in:false_positive,legitimate,escalated,closed',
        ];
    }
}
