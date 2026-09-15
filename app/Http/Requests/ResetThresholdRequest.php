<?php

namespace App\Http\Requests;

use App\Services\ThresholdService;
use Illuminate\Validation\Validator;

/**
 * Validates single-key resets from the admin thresholds page. Authorization
 * is enforced on the route and re-checked in the controller.
 */
class ResetThresholdRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => 'required|string',
            'key' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'category.required' => 'A threshold category is required.',
            'key.required' => 'A threshold key is required.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $defaults = $this->container->make(ThresholdService::class)->configDefaults();
            $category = (string) $this->input('category');
            $key = strtolower((string) $this->input('key'));

            if ($category !== '' && $key !== ''
                && (! isset($defaults[$category]) || ! array_key_exists($key, $defaults[$category]))) {
                $validator->errors()->add('key', "Unknown threshold: {$category}.{$key}");
            }
        });
    }
}
