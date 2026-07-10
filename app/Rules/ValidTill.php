<?php

namespace App\Rules;

use App\Enums\CounterStatus;
use App\Models\Counter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidTill implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail("The {$attribute} must be a string.");

            return;
        }

        $counter = Counter::where('code', $value)
            ->orWhere('id', $value)
            ->first();

        if ($counter === null) {
            $fail("The selected {$attribute} is invalid.");

            return;
        }

        if ($counter->status !== CounterStatus::Active) {
            $fail("The selected {$attribute} is not open.");
        }
    }
}
