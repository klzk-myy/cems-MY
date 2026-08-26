<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a Malaysian MyKad (IC) number.
 *
 * Enforces both the numeric/separator format and the birthdate encoded in
 * the first 6 digits (YYMMDD). This single source replaces the divergent
 * implementations that previously lived in CustomerController and the
 * HasCustomerValidationRules trait.
 */
class MyKadFormatRule implements ValidationRule
{
    /**
     * Days-per-month lookup (index 1..12).
     */
    private const DAYS_IN_MONTH = [
        1 => 31,
        2 => 29, // simplified - catches > 29; full leap-year math not applied
        3 => 31,
        4 => 30,
        5 => 31,
        6 => 30,
        7 => 31,
        8 => 31,
        9 => 30,
        10 => 31,
        11 => 30,
        12 => 31,
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('MyKad ID must be a string.');

            return;
        }

        $idType = request()->input('id_type');

        if ($idType !== 'MyKad') {
            return;
        }

        if (! preg_match('/^\d{6}-\d{2}-\d{4}$/', $value)) {
            $fail('MyKad ID must be in format XXXXXX-XX-XXXX (e.g., 900123-01-2345)');

            return;
        }

        $birthdatePart = substr($value, 0, 6);
        $month = (int) substr($birthdatePart, 2, 2);
        $day = (int) substr($birthdatePart, 4, 2);

        if ($month < 1 || $month > 12) {
            $fail('MyKad ID contains invalid month in birthdate.');

            return;
        }

        if ($day < 1 || $day > 31) {
            $fail('MyKad ID contains invalid day in birthdate.');

            return;
        }

        $maxDay = self::DAYS_IN_MONTH[$month];

        if ($day > $maxDay) {
            $fail("MyKad ID contains invalid day for month {$month}.");
        }
    }
}
