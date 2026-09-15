<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Password complexity rule enforcing BNM-compliant password policy.
 *
 * Validates passwords against config/security.php password policy:
 * - Minimum 8 characters (counted as characters, not bytes)
 * - Maximum 72 bytes (bcrypt truncates longer input)
 * - At least one uppercase letter
 * - At least one lowercase letter
 * - At least one number
 * - At least one symbol
 */
class PasswordComplexityRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = (string) $value;

        $policy = config('security.password', [
            'min_length' => 8,
            'max_bytes' => 72,
            'require_uppercase' => true,
            'require_lowercase' => true,
            'require_numbers' => true,
            'require_symbols' => true,
        ]);

        $minLength = (int) ($policy['min_length'] ?? 8);
        $maxBytes = (int) ($policy['max_bytes'] ?? 72);
        $requireUppercase = (bool) ($policy['require_uppercase'] ?? true);
        $requireLowercase = (bool) ($policy['require_lowercase'] ?? true);
        $requireNumbers = (bool) ($policy['require_numbers'] ?? true);
        $requireSymbols = (bool) ($policy['require_symbols'] ?? true);

        // Check minimum length in characters — strlen() counts bytes, so a
        // handful of multibyte characters could otherwise satisfy the floor.
        if (mb_strlen($password) < $minLength) {
            $fail("The {$attribute} must be at least {$minLength} characters.");

            return;
        }

        // Check maximum length in bytes — bcrypt truncates input at 72 bytes,
        // so anything longer would silently weaken the stored credential.
        if (strlen($password) > $maxBytes) {
            $fail("The {$attribute} must not exceed {$maxBytes} bytes.");

            return;
        }

        // Check uppercase
        if ($requireUppercase && ! preg_match('/[A-Z]/', $password)) {
            $fail("The {$attribute} must contain at least one uppercase letter.");

            return;
        }

        // Check lowercase
        if ($requireLowercase && ! preg_match('/[a-z]/', $password)) {
            $fail("The {$attribute} must contain at least one lowercase letter.");

            return;
        }

        // Check numbers
        if ($requireNumbers && ! preg_match('/[0-9]/', $password)) {
            $fail("The {$attribute} must contain at least one number.");

            return;
        }

        // Check symbols - any non-alphanumeric character counts as a symbol
        if ($requireSymbols && ! preg_match('/[^A-Za-z0-9]/', $password)) {
            $fail("The {$attribute} must contain at least one symbol.");

            return;
        }
    }
}
