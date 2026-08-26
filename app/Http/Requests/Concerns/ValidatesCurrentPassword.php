<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Hash;

/**
 * Shared rule for re-authenticating the current user by password before
 * performing a sensitive operation (MFA disable, enrollment, code
 * regeneration).
 */
trait ValidatesCurrentPassword
{
    /**
     * Validation rules asserting that the submitted password matches the
     * authenticated user's current password.
     *
     * @return array<int, mixed>
     */
    protected function currentPasswordRules(): array
    {
        return [
            'required',
            'string',
            function (string $attribute, mixed $value, Closure $fail): void {
                $user = $this->user();

                if (! $user instanceof User || ! Hash::check((string) $value, $user->password_hash)) {
                    $fail('The provided password is incorrect.');
                }
            },
        ];
    }
}
