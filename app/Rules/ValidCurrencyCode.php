<?php

namespace App\Rules;

use App\Models\Currency;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCurrencyCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail("The {$attribute} must be a string.");

            return;
        }

        $currency = Currency::where('code', $value)->first();

        if ($currency === null) {
            $fail("The selected {$attribute} is invalid.");

            return;
        }

        if (! $currency->is_active) {
            $fail("The selected {$attribute} is inactive.");
        }
    }
}
