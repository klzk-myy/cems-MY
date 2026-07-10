<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidAmountForeign implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            $fail("The {$attribute} must be a number.");

            return;
        }

        if ($value < 0.01) {
            $fail("The {$attribute} must be at least 0.01.");

            return;
        }

        if ($value > 9999999999.9999) {
            $fail("The {$attribute} may not be greater than 9999999999.9999.");
        }
    }
}
