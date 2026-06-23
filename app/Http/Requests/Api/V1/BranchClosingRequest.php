<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\ApiFormRequest;

class BranchClosingRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'initiate' => [],
            'checklist' => [],
            'finalize' => [
                'notes' => 'nullable|string',
            ],
            default => [],
        };
    }
}
