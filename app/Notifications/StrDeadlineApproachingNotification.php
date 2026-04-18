<?php

namespace App\Notifications;

use App\Models\StrReport;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when STR filing deadline is approaching.
 */
class StrDeadlineApproachingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public StrReport $strReport,
        public int $daysRemaining
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        $channels = ['database', 'broadcast'];

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
        $customer = $this->strReport->customer;
        $severity = $this->getSeverity();

        return (new MailMessage)
            ->subject($this->getSubject())
            ->markdown('emails.str-deadline', [
                'notifiable' => $notifiable,
                'strReport' => $this->strReport,
                'customer' => $customer,
                'daysRemaining' => $this->daysRemaining,
                'severity' => $severity,
                'filingDeadline' => $this->strReport->filing_deadline,
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
            'type' => 'str_deadline_approaching',
            'str_report_id' => $this->strReport->id,
            'str_no' => $this->strReport->str_no,
            'customer_id' => $this->strReport->customer_id,
            'customer_name' => $this->strReport->customer?->full_name ?? 'Unknown',
            'days_remaining' => $this->daysRemaining,
            'filing_deadline' => $this->strReport->filing_deadline?->toIso8601String(),
            'status' => $this->strReport->status->value ?? null,
            'severity' => $this->getSeverity(),
            'url' => route('str.show', $this->strReport->id),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'str_deadline_approaching',
            'data' => $this->toArray($notifiable),
            'created_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get the severity level based on days remaining.
     */
    protected function getSeverity(): string
    {
        if ($this->daysRemaining < 0) {
            return 'critical';
        }

        if ($this->daysRemaining <= 1) {
            return 'critical';
        }

        if ($this->daysRemaining <= 2) {
            return 'warning';
        }

        return 'info';
    }

    /**
     * Get the email subject based on severity.
     */
    protected function getSubject(): string
    {
        $prefix = match ($this->getSeverity()) {
            'critical' => '[URGENT]',
            'warning' => '[WARNING]',
            default => '[Notice]',
        };

        return "{$prefix} STR Filing Deadline Approaching - ".config('app.name');
    }

    /**
     * Determine if email should be sent based on user preferences.
     */
    protected function shouldSendEmail(User $notifiable): bool
    {
        $preference = $notifiable->notificationPreferences()
            ->where('notification_type', 'str_deadline_approaching')
            ->first();

        return $preference?->email_enabled ?? true;
    }

    /**
     * Get the notification's database type.
     */
    public function databaseType(User $notifiable): string
    {
        return 'str_deadline_approaching';
    }
}
