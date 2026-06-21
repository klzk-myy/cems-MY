<?php

namespace App\Http\Requests;

class BatchUploadRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ];
    }
}
