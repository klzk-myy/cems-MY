<?php

namespace App\Rules;

use App\Models\PasswordHistory;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

/**
 * Rejects passwords that match any of the user's most recently used
 * passwords. The current hash is included in the checked set so an
 * admin reset cannot silently keep the same working password.
 */
class PasswordNotRecentlyUsed implements ValidationRule
{
    /**
     * Create a new rule instance.
     */
    public function __construct(protected ?User $user) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->user === null || ! $this->user->exists) {
            return;
        }

        $depth = max(0, (int) config('security.password.history_depth', 5));

        if ($depth === 0) {
            return;
        }

        $hashes = PasswordHistory::recentHashesFor($this->user, $depth);

        $currentHash = $this->user->password_hash;

        if (is_string($currentHash) && $currentHash !== '') {
            $hashes->prepend($currentHash);
        }

        foreach ($hashes as $hash) {
            if (is_string($hash) && Hash::check((string) $value, $hash)) {
                $fail("The {$attribute} was recently used.");

                return;
            }
        }
    }
}
