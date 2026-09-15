<?php

namespace App\Rules;

use App\Models\User;

/**
 * Shared password-rule sets so every credential entry point composes the
 * same validation instead of hand-rolling (and drifting on) rule arrays.
 *
 * - forNew(): first-time credentials (user create, setup wizard) — no
 *   history check, a new account has none
 * - forChange(): rotation/reset on an existing account — adds recent-history
 *   reuse prevention covering the current hash
 */
final class PasswordRules
{
    /**
     * @return array<int, mixed>
     */
    public static function forNew(bool $confirmed = true): array
    {
        return array_values(array_filter([
            'required',
            'string',
            $confirmed ? 'confirmed' : null,
            new PasswordComplexityRule,
        ]));
    }

    /**
     * @return array<int, mixed>
     */
    public static function forChange(?User $user, bool $confirmed = true): array
    {
        return array_values(array_filter([
            'required',
            'string',
            $confirmed ? 'confirmed' : null,
            new PasswordComplexityRule,
            new PasswordNotRecentlyUsed($user),
        ]));
    }
}
