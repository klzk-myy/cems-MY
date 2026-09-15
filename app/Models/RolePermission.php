<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Role Permission Model
 *
 * One row per role+permission pair in the role_permissions matrix. The
 * matrix is the authoritative grant set for dynamic permissions, seeded
 * from the built-in UserRole defaults — see UserRole::canPerform() for
 * the effective check.
 *
 * @property int $id
 * @property string $role
 * @property string $permission
 * @property bool $granted
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RolePermission extends BaseModel
{
    protected $table = 'role_permissions';

    protected $fillable = [
        'role',
        'permission',
        'granted',
        'updated_by',
    ];

    protected $casts = [
        'granted' => 'boolean',
    ];

    /**
     * The user who last updated this permission.
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to permissions for a specific role.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    /**
     * Scope to a specific permission key.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForPermission(Builder $query, string $permission): Builder
    {
        return $query->where('permission', $permission);
    }

    /**
     * Scope to only granted permissions.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeGranted(Builder $query): Builder
    {
        return $query->where('granted', true);
    }
}
