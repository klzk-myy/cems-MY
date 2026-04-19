<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Services\MfaService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * User Model
 *
 * Represents system users with role-based access control.
 * Supports multi-factor authentication and activity tracking.
 *
 * @property int $id
 * @property int|null $branch_id
 * @property string $username
 * @property string $email
 * @property string $password_hash
 * @property UserRole $role
 * @property bool $mfa_enabled
 * @property string|null $mfa_secret
 * @property Carbon|null $mfa_verified_at
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'branch_id',
        'username',
        'email',
        'password',
        'password_hash',
        'role',
        'mfa_enabled',
        'mfa_secret',
        'mfa_verified_at',
        'is_active',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [
        'password_hash',
        'mfa_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'role' => UserRole::class,
        'mfa_enabled' => 'boolean',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'mfa_verified_at' => 'datetime',
    ];

    /**
     * Get the password for authentication.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /**
     * Alias for password_hash to satisfy Laravel's default authentication expectations.
     */
    public function getPasswordAttribute(): string
    {
        return $this->password_hash;
    }

    /**
     * When password is set, automatically hash it and store in password_hash.
     *
     * @param  string  $value
     */
    public function setPasswordAttribute($value): void
    {
        $this->password_hash = Hash::make($value);
    }

    /**
     * Get all transactions created by this user.
     *
     * @return HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the branch this user belongs to.
     *
     * @return BelongsTo
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Check if user has admin role.
     *
     * @return bool True if user has admin role
     */
    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    /**
     * Check if user has manager or admin role.
     *
     * @return bool True if user has manager or admin role
     */
    public function isManager(): bool
    {
        return $this->role->isManager();
    }

    /**
     * Check if user has compliance officer or admin role.
     *
     * @return bool True if user has compliance officer or admin role
     */
    public function isComplianceOfficer(): bool
    {
        return $this->role->isComplianceOfficer();
    }

    /**
     * Check if MFA is verified for this session.
     */
    public function isMfaVerified(): bool
    {
        if (! $this->mfa_enabled) {
            return true; // MFA not enabled, consider verified
        }

        return $this->mfa_verified_at !== null;
    }

    /**
     * Get recovery codes for this user.
     */
    public function mfaRecoveryCodes(): HasMany
    {
        return $this->hasMany(MfaRecoveryCode::class);
    }

    /**
     * Get trusted devices for this user.
     */
    public function trustedDevices(): HasMany
    {
        return $this->hasMany(DeviceComputations::class);
    }

    /**
     * Check if user needs to set up MFA (based on role and grace period).
     */
    public function needsMfaSetup(): bool
    {
        if ($this->mfa_enabled) {
            return false;
        }

        // Check if role requires MFA
        $mfaService = app(MfaService::class);
        if (! $mfaService->isMfaRequiredForRole($this)) {
            return false;
        }

        // Check grace period (if first login is within grace period)
        $graceDays = config('cems.mfa.grace_days', 30);
        if ($this->last_login_at && $this->last_login_at->diffInDays(now()) > $graceDays) {
            return true;
        }

        // First login - within grace period doesn't need setup yet
        return false;
    }

    /**
     * Check if user's MFA session has expired.
     */
    public function isMfaSessionExpired(): bool
    {
        if (! $this->mfa_enabled) {
            return false;
        }

        // MFA verification is per-session, so this is always false after verification
        // The session expiry is handled by Laravel's session management
        return false;
    }

    /**
     * Get notification preferences for this user.
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(UserNotificationPreference::class);
    }

    /**
     * Get notification preference for a specific notification type.
     * Creates default preference if not exists.
     */
    public function getNotificationPreference(string $type): UserNotificationPreference
    {
        $preference = $this->notificationPreferences()
            ->where('notification_type', $type)
            ->first();

        if (! $preference) {
            $defaults = UserNotificationPreference::getDefaultPreferences()[$type] ?? [
                'email_enabled' => true,
                'sms_enabled' => false,
                'in_app_enabled' => true,
                'push_enabled' => false,
            ];

            $preference = $this->notificationPreferences()->create([
                'notification_type' => $type,
                'email_enabled' => $defaults['email_enabled'],
                'sms_enabled' => $defaults['sms_enabled'],
                'in_app_enabled' => $defaults['in_app_enabled'],
                'push_enabled' => $defaults['push_enabled'],
            ]);
        }

        return $preference;
    }

    /**
     * Check if this user can view a teller's allocation.
     * Managers can view allocations of tellers in their branch; admins can view all.
     */
    public function canViewTellerAllocation(User $teller): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isManager()) {
            return $this->branch_id === $teller->branch_id;
        }

        return $this->id === $teller->id;
    }

    /**
     * Check if this user can view branch pools for a given branch.
     * Managers can view pools for their own branch; admins can view all.
     */
    public function canViewBranchPools(Branch $branch): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isManager()) {
            return $this->branch_id === $branch->id;
        }

        return false;
    }

    /**
     * Check if this user can modify a teller's allocation.
     * Managers can modify allocations of tellers in their branch; admins can modify all.
     */
    public function canModifyAllocation(User $teller): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isManager()) {
            return $this->branch_id === $teller->branch_id;
        }

        return false;
    }

    /**
     * Check if a notification channel is enabled for this user.
     */
    public function isNotificationChannelEnabled(string $type, string $channel): bool
    {
        $preference = $this->getNotificationPreference($type);

        return match ($channel) {
            'mail', 'email' => $preference->isEmailEnabled(),
            'sms' => $preference->isSmsEnabled(),
            'database', 'in_app' => $preference->isInAppEnabled(),
            'broadcast', 'push' => $preference->isPushEnabled(),
            default => true,
        };
    }
}
