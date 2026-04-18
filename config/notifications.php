<?php

/**
 * Notification System Configuration
 *
 * CEMS-MY Laravel Application - Bank Negara Malaysia MSB Compliant
 *
 * This configuration file controls the notification system settings
 * including channels, preferences, and compliance-specific options.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Default Notification Channels
    |--------------------------------------------------------------------------
    |
    | These are the default channels that will be used when sending
    | notifications. Available channels: mail, sms, database, broadcast, webhook
    |
    */
    'default_channels' => ['database', 'broadcast'],

    /*
    |--------------------------------------------------------------------------
    | Critical Notification Channels
    |--------------------------------------------------------------------------
    |
    | These channels are used for critical notifications (data breaches,
    | sanctions matches, STR failures) and cannot be disabled by users.
    |
    */
    'critical_channels' => ['database', 'broadcast', 'mail', 'sms'],

    /*
    |--------------------------------------------------------------------------
    | SMS Configuration (Twilio)
    |--------------------------------------------------------------------------
    |
    | Configuration for SMS notifications via Twilio.
    | Set enabled to true and configure credentials to enable SMS.
    |
    */
    'sms' => [
        'enabled' => env('SMS_ENABLED', false),
        'driver' => env('SMS_DRIVER', 'twilio'),
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for webhook notifications to external systems.
    | Useful for integrating with SIEM, monitoring, or compliance systems.
    |
    */
    'webhook' => [
        'enabled' => env('WEBHOOK_NOTIFICATIONS_ENABLED', false),
        'default_url' => env('WEBHOOK_NOTIFICATIONS_URL'),
        'secret' => env('WEBHOOK_NOTIFICATIONS_SECRET'),
        'timeout' => 30,
        'retry_attempts' => 3,
        'retry_delay' => 60, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Types
    |--------------------------------------------------------------------------
    |
    | Definitions of all notification types with their default channel settings.
    | Users can override these via their preferences.
    |
    */
    'types' => [
        'transaction_flagged' => [
            'label' => 'Transaction Flagged',
            'description' => 'Sent when a transaction is flagged for compliance review',
            'default_channels' => ['database', 'broadcast', 'mail'],
            'category' => 'compliance',
            'priority' => 'high',
        ],
        'str_deadline_approaching' => [
            'label' => 'STR Deadline Approaching',
            'description' => 'Sent when STR filing deadline is within 3 days',
            'default_channels' => ['database', 'broadcast', 'mail'],
            'category' => 'compliance',
            'priority' => 'critical',
        ],
        'str_submission_failed' => [
            'label' => 'STR Submission Failed',
            'description' => 'Sent when STR submission to BNM fails',
            'default_channels' => ['database', 'broadcast', 'mail', 'sms'],
            'category' => 'compliance',
            'priority' => 'critical',
            'cannot_disable' => ['mail', 'sms'],
        ],
        'compliance_case_assigned' => [
            'label' => 'Compliance Case Assigned',
            'description' => 'Sent when a compliance case is assigned to an officer',
            'default_channels' => ['database', 'broadcast', 'mail'],
            'category' => 'compliance',
            'priority' => 'high',
        ],
        'data_breach_alert' => [
            'label' => 'Data Breach Alert',
            'description' => 'Sent when a potential data breach is detected',
            'default_channels' => ['database', 'broadcast', 'mail', 'sms'],
            'category' => 'security',
            'priority' => 'critical',
            'cannot_disable' => ['mail', 'sms'],
        ],
        'large_transaction' => [
            'label' => 'Large Transaction',
            'description' => 'Sent when a large transaction requires manager approval',
            'default_channels' => ['database', 'broadcast', 'mail'],
            'category' => 'operations',
            'priority' => 'medium',
        ],
        'sanctions_match' => [
            'label' => 'Sanctions Match',
            'description' => 'Sent when a sanctions screening match is detected',
            'default_channels' => ['database', 'broadcast', 'mail', 'sms'],
            'category' => 'compliance',
            'priority' => 'critical',
            'cannot_disable' => ['mail', 'sms'],
        ],
        'system_health_alert' => [
            'label' => 'System Health Alert',
            'description' => 'Sent for system health issues',
            'default_channels' => ['database', 'broadcast', 'mail'],
            'category' => 'system',
            'priority' => 'medium',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Digest Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for notification digest emails.
    |
    */
    'digest' => [
        'enabled' => env('NOTIFICATION_DIGEST_ENABLED', true),
        'frequency' => env('NOTIFICATION_DIGEST_FREQUENCY', 'daily'), // daily, weekly
        'time' => env('NOTIFICATION_DIGEST_TIME', '09:00'),
        'max_notifications' => 50,
        'include_read' => false,
        'group_by_type' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Cleanup
    |--------------------------------------------------------------------------
    |
    | Configuration for cleaning up old notifications.
    |
    */
    'cleanup' => [
        'enabled' => env('NOTIFICATION_CLEANUP_ENABLED', true),
        'read_retention_days' => 90,
        'unread_retention_days' => 365,
        'run_at' => '02:00',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Queue settings for notification processing.
    |
    */
    'queue' => [
        'enabled' => env('NOTIFICATION_QUEUE_ENABLED', true),
        'connection' => env('QUEUE_CONNECTION', 'database'),
        'queue' => 'notifications',
        'retry_after' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limits for notification sending to prevent spam.
    |
    */
    'rate_limiting' => [
        'enabled' => true,
        'max_per_minute' => 60,
        'max_per_hour' => 500,
        'max_per_day' => 2000,
    ],

    /*
    |--------------------------------------------------------------------------
    | BNM Compliance Settings
    |--------------------------------------------------------------------------
    |
    | Specific settings for BNM MSB compliance requirements.
    |
    */
    'bnm_compliance' => [
        // STR must be submitted within 3 working days
        'str_deadline_days' => 3,
        // Large transaction threshold (RM)
        'large_transaction_threshold' => 50000,
        // Notification retention for audit purposes
        'retention_days' => 2555, // 7 years
    ],

];
