<?php

namespace App\Notifications;

use App\Models\SystemAlert;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent for system health alerts.
 */
class SystemHealthAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public SystemAlert $systemAlert
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        // Only send email for warning and critical alerts
        if ($this->shouldSendEmail($notifiable) && $this->isEmailWorthy()) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $level = $this->systemAlert->level;

        $mailMessage = (new MailMessage);

        // Set message style based on level
        if ($level === SystemAlert::LEVEL_CRITICAL) {
            $mailMessage->error();
        } elseif ($level === SystemAlert::LEVEL_WARNING) {
            $mailMessage->line('warning');
        }

        return $mailMessage
            ->subject($this->getSubject())
            ->markdown('emails.system-health', [
                'notifiable' => $notifiable,
                'systemAlert' => $this->systemAlert,
                'level' => $level,
                'levelLabel' => ucfirst($level),
                'message' => $this->systemAlert->message,
                'source' => $this->systemAlert->source,
                'metadata' => $this->systemAlert->metadata,
                'createdAt' => $this->systemAlert->created_at,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'type' => 'system_health_alert',
            'alert_id' => $this->systemAlert->id,
            'level' => $this->systemAlert->level,
            'level_label' => ucfirst($this->systemAlert->level),
            'message' => $this->systemAlert->message,
            'source' => $this->systemAlert->source,
            'metadata' => $this->systemAlert->metadata,
            'is_acknowledged' => $this->systemAlert->isAcknowledged(),
            'acknowledged_by' => $this->systemAlert->acknowledged_by,
            'acknowledged_at' => $this->systemAlert->acknowledged_at?->toIso8601String(),
            'url' => route('system-alerts.show', $this->systemAlert->id),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'system_health_alert',
            'data' => $this->toArray($notifiable),
            'created_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get the email subject based on alert level.
     */
    protected function getSubject(): string
    {
        $prefix = match ($this->systemAlert->level) {
            SystemAlert::LEVEL_CRITICAL => '[CRITICAL]',
            SystemAlert::LEVEL_WARNING => '[WARNING]',
            default => '[INFO]',
        };

        return "{$prefix} System Health Alert - ".config('app.name');
    }

    /**
     * Determine if this alert is worthy of an email notification.
     */
    protected function isEmailWorthy(): bool
    {
        return in_array($this->systemAlert->level, [
            SystemAlert::LEVEL_WARNING,
            SystemAlert::LEVEL_CRITICAL,
        ]);
    }

    /**
     * Determine if email should be sent based on user preferences.
     */
    protected function shouldSendEmail(User $notifiable): bool
    {
        $preference = $notifiable->notificationPreferences()
            ->where('notification_type', 'system_health_alert')
            ->first();

        return $preference?->email_enabled ?? true;
    }

    /**
     * Get the notification's database type.
     */
    public function databaseType(User $notifiable): string
    {
        return 'system_health_alert';
    }
}
