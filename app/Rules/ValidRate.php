<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidRate implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            $fail("The {$attribute} must be a number.");

            return;
        }

        if ($value < 0.0001) {
            $fail("The {$attribute} must be at least 0.0001.");

            return;
        }

        if ($value > 999999) {
            $fail("The {$attribute} may not be greater than 999999.");
        }
    }
}
