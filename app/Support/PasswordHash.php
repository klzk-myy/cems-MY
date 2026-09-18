<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Fail-closed password-hash verification.
 *
 * A hash stored in a foreign format (e.g. bcrypt under the Argon2id
 * driver, or a legacy hash imported from another system) makes
 * Hash::check throw RuntimeException — that must fail closed as a
 * mismatch, not a 500 on an auth or step-up path.
 */
final class PasswordHash
{
    public static function check(string $plain, ?string $hash): bool
    {
        if ($hash === null || $hash === '') {
            return false;
        }

        try {
            return Hash::check($plain, $hash);
        } catch (\RuntimeException $e) {
            Log::warning('Password check rejected: unrecognised hash format', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
