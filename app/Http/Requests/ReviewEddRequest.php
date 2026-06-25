<?php

namespace App\Http\Requests;

class ReviewEddRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'decision' => 'required|in:approved,rejected,additional_info',
            'review_notes' => 'required|string|max:5000',
        ];
    }
}
