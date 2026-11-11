<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class AuthorizedFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Split a newline/comma-separated recipient field into a clean email
     * list and merge it back, so validation always sees an array.
     */
    protected function mergeRecipientList(string $field): void
    {
        if (! is_string($this->input($field))) {
            return;
        }

        $emails = array_values(array_filter(array_map(
            'trim',
            preg_split('/[\n,]+/', (string) $this->input($field)) ?: []
        )));

        $this->merge([$field => $emails]);
    }
}
