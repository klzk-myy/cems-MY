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

        $user = auth()->user();

        // The rule is only meaningful for an authenticated, branch-assigned
        // user: without both, branch scoping cannot be applied. Fail closed
        // rather than falling back to an unscoped lookup — HTTP routes are
        // auth-guarded, so an unauthenticated caller must never resolve a
        // counter code.
        if ($user === null) {
            $fail("The selected {$attribute} could not be validated for an unauthenticated request.");

            return;
        }

        if ($user->branch_id === null) {
            $fail("The selected {$attribute} could not be validated without an assigned branch.");

            return;
        }

        $counter = Counter::query()
            ->where(function ($query) use ($value) {
                $query->where('code', $value)->orWhere('id', $value);
            })
            ->where('branch_id', $user->branch_id)
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
