<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * User Notification Preference Model
 *
 * Stores user preferences for notification channels and types.
 * Allows users to customize how they receive different types of notifications.
 *
 * @property int $id
 * @property int $user_id
 * @property string $notification_type
 * @property bool $email_enabled
 * @property bool $sms_enabled
 * @property bool $in_app_enabled
 * @property bool $push_enabled
 * @property string|null $webhook_url
 * @property array|null $custom_settings
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class UserNotificationPreference extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_notification_preferences';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'notification_type',
        'email_enabled',
        'sms_enabled',
        'in_app_enabled',
        'push_enabled',
        'webhook_url',
        'custom_settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
        'push_enabled' => 'boolean',
        'custom_settings' => 'array',
    ];

    /**
     * Get the user that owns this preference.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get default preferences for all notification types.
     *
     * @return array<string, array>
     */
    public static function getDefaultPreferences(): array
    {
        return [
            // Critical compliance notifications - all channels enabled by default
            'transaction_flagged' => [
                'email_enabled' => true,
                'sms_enabled' => false,
                'in_app_enabled' => true,
                'push_enabled' => false,
            ],
            'str_deadline_approaching' => [
                'email_enabled' => true,
                'sms_enabled' => false,
                'in_app_enabled' => true,
                'push_enabled' => true,
            ],
            'str_submission_failed' => [
                'email_enabled' => true,
                'sms_enabled' => true,
                'in_app_enabled' => true,
                'push_enabled' => true,
            ],
            'compliance_case_assigned' => [
                'email_enabled' => true,
                'sms_enabled' => false,
                'in_app_enabled' => true,
                'push_enabled' => true,
            ],
            'data_breach_alert' => [
                'email_enabled' => true,
                'sms_enabled' => true,
                'in_app_enabled' => true,
                'push_enabled' => true,
            ],
            'large_transaction' => [
                'email_enabled' => true,
                'sms_enabled' => false,
                'in_app_enabled' => true,
                'push_enabled' => false,
            ],
            'sanctions_match' => [
                'email_enabled' => true,
                'sms_enabled' => true,
                'in_app_enabled' => true,
                'push_enabled' => true,
            ],
            'system_health_alert' => [
                'email_enabled' => true,
                'sms_enabled' => false,
                'in_app_enabled' => true,
                'push_enabled' => false,
            ],
        ];
    }

    /**
     * Get all available notification types.
     *
     * @return array<string, string>
     */
    public static function getNotificationTypes(): array
    {
        return [
            'transaction_flagged' => 'Transaction Flagged',
            'str_deadline_approaching' => 'STR Deadline Approaching',
            'str_submission_failed' => 'STR Submission Failed',
            'compliance_case_assigned' => 'Compliance Case Assigned',
            'data_breach_alert' => 'Data Breach Alert',
            'large_transaction' => 'Large Transaction',
            'sanctions_match' => 'Sanctions Match',
            'system_health_alert' => 'System Health Alert',
        ];
    }

    /**
     * Get notification type label.
     */
    public static function getNotificationTypeLabel(string $type): string
    {
        return self::getNotificationTypes()[$type] ?? ucwords(str_replace('_', ' ', $type));
    }

    /**
     * Scope to get preferences for a specific notification type.
     */
    public function scopeForType($query, string $type)
    {
        return $query->where('notification_type', $type);
    }

    /**
     * Scope to get preferences for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if email is enabled for this notification type.
     */
    public function isEmailEnabled(): bool
    {
        return $this->email_enabled;
    }

    /**
     * Check if SMS is enabled for this notification type.
     */
    public function isSmsEnabled(): bool
    {
        return $this->sms_enabled;
    }

    /**
     * Check if in-app notifications are enabled.
     */
    public function isInAppEnabled(): bool
    {
        return $this->in_app_enabled;
    }

    /**
     * Check if push notifications are enabled.
     */
    public function isPushEnabled(): bool
    {
        return $this->push_enabled;
    }

    /**
     * Enable all channels for this notification type.
     */
    public function enableAllChannels(): void
    {
        $this->update([
            'email_enabled' => true,
            'sms_enabled' => true,
            'in_app_enabled' => true,
            'push_enabled' => true,
        ]);
    }

    /**
     * Disable all channels for this notification type.
     */
    public function disableAllChannels(): void
    {
        $this->update([
            'email_enabled' => false,
            'sms_enabled' => false,
            'in_app_enabled' => false,
            'push_enabled' => false,
        ]);
    }

    /**
     * Update a specific channel setting.
     */
    public function updateChannel(string $channel, bool $enabled): void
    {
        $validChannels = ['email', 'sms', 'in_app', 'push'];

        if (! in_array($channel, $validChannels)) {
            throw new \InvalidArgumentException("Invalid channel: {$channel}");
        }

        $this->update([
            "{$channel}_enabled" => $enabled,
        ]);
    }
}
