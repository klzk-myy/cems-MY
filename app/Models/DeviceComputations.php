<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $device_name
 * @property string $device_fingerprint
 * @property string|null $ip_address
 * @property Carbon|null $expires_at
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DeviceComputations extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_name',
        'device_fingerprint',
        'ip_address',
        'expires_at',
        'last_used_at',
    ];

    protected $hidden = [
        'device_fingerprint',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /**
     * Get the user that owns the trusted device.
     */
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the device is still valid (not expired).
     *
     * A NULL expiry is treated as invalid: every remembered device is stamped
     * with an expiry, so a row without one is a legacy/backfilled artifact and
     * must not grant a permanent MFA bypass.
     */
    public function isValid(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isFuture();
    }
}
