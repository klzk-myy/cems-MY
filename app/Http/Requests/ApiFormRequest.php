<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class ApiFormRequest extends AuthorizedFormRequest
{
    /**
     * Form-request validation only inspects body/query by default. API
     * routes that carry `{date}` in the path need it merged in so its
     * `date_format` rule can run.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        $data = parent::validationData();

        $routeDate = $this->route('date');
        if ($routeDate !== null) {
            $data['date'] = $routeDate;
        }

        return $data;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422));
    }
}
