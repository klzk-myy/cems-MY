<?php

namespace App\Http\Requests;

class UploadCaseDocumentRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'A document file must be selected for upload.',
            'file.mimes' => 'The file must be a PDF, Word document, or image (JPG/PNG).',
            'file.max' => 'The file size must not exceed 10MB.',
        ];
    }
}
