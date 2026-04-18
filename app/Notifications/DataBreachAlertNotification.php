<?php

namespace App\Notifications;

use App\Models\DataBreachAlert;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a data breach is detected.
 */
class DataBreachAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public DataBreachAlert $dataBreachAlert
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        // Always send email for data breaches - critical security issue
        if ($this->shouldSendEmail($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $severity = $this->dataBreachAlert->severity;

        return (new MailMessage)
            ->error()
            ->subject('[SECURITY ALERT] Data Breach Detected - '.config('app.name'))
            ->markdown('emails.data-breach', [
                'notifiable' => $notifiable,
                'dataBreachAlert' => $this->dataBreachAlert,
                'alertType' => $this->dataBreachAlert->alert_type,
                'severity' => $severity,
                'description' => $this->dataBreachAlert->description,
                'recordCount' => $this->dataBreachAlert->record_count,
                'triggeredBy' => $this->dataBreachAlert->triggerUser,
                'ipAddress' => $this->dataBreachAlert->ip_address,
                'createdAt' => $this->dataBreachAlert->created_at,
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
            'type' => 'data_breach_alert',
            'alert_id' => $this->dataBreachAlert->id,
            'alert_type' => $this->dataBreachAlert->alert_type,
            'severity' => $this->dataBreachAlert->severity,
            'description' => $this->dataBreachAlert->description,
            'record_count' => $this->dataBreachAlert->record_count,
            'triggered_by' => $this->dataBreachAlert->triggered_by,
            'triggered_by_name' => $this->dataBreachAlert->triggerUser?->username ?? 'Unknown',
            'ip_address' => $this->dataBreachAlert->ip_address,
            'is_resolved' => $this->dataBreachAlert->is_resolved,
            'url' => route('data-breach-alerts.show', $this->dataBreachAlert->id),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'data_breach_alert',
            'data' => $this->toArray($notifiable),
            'created_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Determine if email should be sent based on user preferences.
     */
    protected function shouldSendEmail(User $notifiable): bool
    {
        $preference = $notifiable->notificationPreferences()
            ->where('notification_type', 'data_breach_alert')
            ->first();

        // Data breach alerts are critical - default to true
        return $preference?->email_enabled ?? true;
    }

    /**
     * Get the notification's database type.
     */
    public function databaseType(User $notifiable): string
    {
        return 'data_breach_alert';
    }
}
