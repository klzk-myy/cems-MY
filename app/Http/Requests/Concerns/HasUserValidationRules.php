<?php

namespace App\Http\Requests\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Shared validation rules for user store/update forms.
 */
trait HasUserValidationRules
{
    /**
     * Get the common user validation rules.
     *
     * @param  bool  $isUpdate  When true, unique rules ignore the route model.
     */
    protected function userValidationRules(bool $isUpdate = false): array
    {
        $unique = fn (string $column) => $isUpdate
            ? Rule::unique('users', $column)->ignore($this->route('user'))
            : Rule::unique('users', $column);

        return [
            'username' => ['required', 'string', 'max:50', $unique('username')],
            'email' => ['required', 'email', 'max:255', $unique('email')],
            'role' => ['required', 'string', Rule::in($this->assignableRoleValues())],
            // Branch scope for the user. Branch operating roles (teller,
            // manager) require a home branch — enforced for admins, who are
            // the only actors able to leave the field empty; non-admin
            // actors always have their own branch forced downstream. NULL
            // stays valid for office roles (accountant, compliance).
            'branch_id' => [
                Rule::requiredIf(fn () => $this->user()?->isAdmin()
                    && (UserRole::tryFrom((string) $this->input('role'))?->requiresBranch() ?? false)),
                'nullable',
                'integer',
                Rule::exists('branches', 'id'),
            ],
        ];
    }

    /**
     * Role values the acting user may submit, per UserRole::assignableRoles().
     *
     * The whitelist is scoped to the requester: managers can only submit
     * 'teller', so a forged POST cannot escalate a user past the acting
     * role's assignable set. When editing one's own account the current role
     * is the only acceptable value — role self-changes are rejected again in
     * UserService as a second layer.
     *
     * @return list<string>
     */
    private function assignableRoleValues(): array
    {
        $actor = $this->user();
        $target = $this->route('user');

        if ($target instanceof User && $actor !== null && $target->id === $actor->id) {
            return [$target->role->value];
        }

        return array_map(
            fn (UserRole $role) => $role->value,
            $actor?->role->assignableRoles() ?? []
        );
    }
}
