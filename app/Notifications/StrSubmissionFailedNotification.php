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
 * Notification sent when STR submission to BNM fails.
 */
class StrSubmissionFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public StrReport $strReport,
        public string $errorMessage,
        public int $retryCount
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        // Always send email for failed submissions - critical compliance issue
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

        return (new MailMessage)
            ->error()
            ->subject('[CRITICAL] STR Submission Failed - '.config('app.name'))
            ->markdown('emails.str-submission-failed', [
                'notifiable' => $notifiable,
                'strReport' => $this->strReport,
                'customer' => $customer,
                'errorMessage' => $this->errorMessage,
                'retryCount' => $this->retryCount,
                'maxRetries' => config('cems.str.max_retries', 3),
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
            'type' => 'str_submission_failed',
            'str_report_id' => $this->strReport->id,
            'str_no' => $this->strReport->str_no,
            'customer_id' => $this->strReport->customer_id,
            'customer_name' => $this->strReport->customer?->full_name ?? 'Unknown',
            'error_message' => $this->errorMessage,
            'retry_count' => $this->retryCount,
            'max_retries' => config('cems.str.max_retries', 3),
            'status' => $this->strReport->status->value ?? null,
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
            'type' => 'str_submission_failed',
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
            ->where('notification_type', 'str_submission_failed')
            ->first();

        return $preference?->email_enabled ?? true;
    }

    /**
     * Get the notification's database type.
     */
    public function databaseType(User $notifiable): string
    {
        return 'str_submission_failed';
    }
}
