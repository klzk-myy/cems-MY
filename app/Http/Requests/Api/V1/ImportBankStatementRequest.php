<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\AuthorizedFormRequest;

class ImportBankStatementRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'csv_file.required' => 'Please upload a CSV file.',
            'csv_file.file' => 'The uploaded file is not valid.',
            'csv_file.mimes' => 'The file must be a CSV or TXT file.',
            'csv_file.max' => 'The file must not exceed 2048 KB.',
        ];
    }
}
