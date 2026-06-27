<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\ApiFormRequest;

class UploadDocumentRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'document_type' => 'required|string|max:100',
        ];
    }
}
