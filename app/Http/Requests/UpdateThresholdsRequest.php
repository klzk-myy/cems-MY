<?php

namespace App\Http\Requests;

use App\Models\Currency;
use App\Services\ThresholdService;
use App\Support\ThresholdMetadata;
use Illuminate\Validation\Validator;

/**
 * Validates bulk threshold edits from the admin thresholds page.
 *
 * Authorization is enforced on the route (role:manage_thresholds +
 * mfa.verified + password.confirm) and re-checked in the controller; this
 * request only validates shape, key whitelists, and ordering constraints.
 */
class UpdateThresholdsRequest extends AuthorizedFormRequest
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
            'reason' => 'required|string|max:255',
            'values' => 'sometimes|array',
            'values.*' => 'array',
            'values.*.*' => ['nullable', 'regex:/^\d+(\.\d+)?$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A change reason is required for threshold updates.',
            'reason.max' => 'The change reason may not exceed 255 characters.',
            'values.*.*.regex' => 'Threshold values must be non-negative numbers.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateKnownKeys($validator);
            $this->validateOrdering($validator);
        });
    }

    /**
     * Reject categories/keys that are neither config-defined nor already
     * persisted overrides — the form must not mint arbitrary threshold keys.
     * position_limits additionally accepts keys matching a currencies-table
     * code, so limits for newly added currencies can be set from the page.
     */
    private function validateKnownKeys(Validator $validator): void
    {
        $service = $this->container->make(ThresholdService::class);
        $defaults = $service->configDefaults();
        $persistedKeys = [];
        foreach (array_keys($service->latestOverrides()) as $compound) {
            $persistedKeys[$compound] = true;
        }

        $currencyCodes = null;

        foreach ($this->input('values', []) as $category => $keys) {
            if (! is_array($keys)) {
                continue;
            }

            if (! isset($defaults[$category])) {
                $validator->errors()->add("values.{$category}", "Unknown threshold category: {$category}");

                continue;
            }

            if ($category === 'position_limits') {
                $currencyCodes ??= Currency::query()->pluck('code')
                    ->map(fn ($code) => strtolower((string) $code))
                    ->flip()
                    ->all();
            }

            foreach (array_keys($keys) as $key) {
                $known = array_key_exists($key, $defaults[$category])
                    || isset($persistedKeys["{$category}.{$key}"])
                    || ($category === 'position_limits' && isset($currencyCodes[strtolower((string) $key)]));

                if (! $known) {
                    $validator->errors()->add(
                        "values.{$category}.{$key}",
                        "Unknown threshold: {$category}.{$key}"
                    );
                }
            }
        }
    }

    /**
     * Enforce the ordering chains declared in ThresholdMetadata (e.g.
     * cdd.specific <= cdd.standard <= cdd.large_transaction). Submitted
     * values are merged over current effective values so partial edits are
     * checked against the resulting configuration, not just each other.
     */
    private function validateOrdering(Validator $validator): void
    {
        $service = $this->container->make(ThresholdService::class);
        $defaults = $service->configDefaults();
        $overrides = $service->latestOverrides();
        $submitted = $this->input('values', []);

        foreach (array_keys(ThresholdMetadata::categories()) as $category) {
            foreach (ThresholdMetadata::orderedChains($category) as $chain) {
                $effective = [];
                foreach ($chain as $key) {
                    $value = $submitted[$category][$key] ?? null;
                    if ($value === null || $value === '') {
                        $value = $overrides["{$category}.{$key}"]->new_value
                            ?? $defaults[$category][$key]
                            ?? null;
                    }
                    $effective[$key] = $value;
                }

                $previousKey = null;
                foreach ($chain as $key) {
                    $current = $effective[$key];
                    $previous = $previousKey !== null ? $effective[$previousKey] : null;
                    if (is_numeric($current) && is_numeric($previous)
                        && bccomp((string) $previous, (string) $current, 10) === 1) {
                        $validator->errors()->add(
                            "values.{$category}.{$key}",
                            "{$category}.{$key} must be greater than or equal to {$category}.{$previousKey}."
                        );
                    }
                    $previousKey = $key;
                }
            }
        }
    }
}
