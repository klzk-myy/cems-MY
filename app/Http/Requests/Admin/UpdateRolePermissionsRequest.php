<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        // Matrix check, matching the route's role middleware — an identity
        // check here would override the matrix so a granted role could
        // never reach the action.
        return $user !== null && $user->role->canPerform(Permission::ManageRolePermissions);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['nullable', Rule::in(['default'])],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['nullable', 'string'],
        ];
    }

    /**
     * True when the Default action was submitted.
     */
    public function isDefaultReset(): bool
    {
        return $this->input('action') === 'default';
    }

    /**
     * Expand the checkbox grid into a permission matrix.
     * Each submitted role maps to every valid permission key; checked = granted.
     * Roles absent from the submission are omitted so a partial payload cannot
     * silently revoke grants it never mentioned.
     *
     * @return array<string, array<string, bool>>
     */
    public function matrix(): array
    {
        $submitted = $this->input('permissions', []);
        $submitted = is_array($submitted) ? $submitted : [];

        $result = [];
        foreach (UserRole::cases() as $role) {
            if (! array_key_exists($role->value, $submitted)) {
                continue;
            }
            $result[$role->value] = [];
            foreach (Permission::cases() as $permission) {
                $result[$role->value][$permission->value] = $this->has("permissions.{$role->value}.{$permission->value}");
            }
        }

        return $result;
    }
}
