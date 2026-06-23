<?php

namespace App\Notifications;

use App\Models\FlaggedTransaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a transaction is flagged for compliance review.
 */
class TransactionFlaggedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public FlaggedTransaction $flaggedTransaction,
        public ?User $flaggedBy = null
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
        $transaction = $this->flaggedTransaction->transaction;
        $customer = $this->flaggedTransaction->customer;

        return (new MailMessage)
            ->subject('Transaction Flagged for Review - '.config('app.name'))
            ->markdown('emails.transaction-flagged', [
                'notifiable' => $notifiable,
                'flaggedTransaction' => $this->flaggedTransaction,
                'transaction' => $transaction,
                'customer' => $customer,
                'flaggedBy' => $this->flaggedBy,
                'flagType' => $this->flaggedTransaction->flag_type->value ?? 'Unknown',
                'flagReason' => $this->flaggedTransaction->flag_reason,
                'url' => route('compliance.flags.resolve', $this->flaggedTransaction->id),
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
            'type' => 'transaction_flagged',
            'flagged_transaction_id' => $this->flaggedTransaction->id,
            'transaction_id' => $this->flaggedTransaction->transaction_id,
            'customer_id' => $this->flaggedTransaction->customer_id,
            'customer_name' => $this->flaggedTransaction->customer?->full_name ?? 'Unknown',
            'flag_type' => $this->flaggedTransaction->flag_type->value ?? null,
            'flag_reason' => $this->flaggedTransaction->flag_reason,
            'status' => $this->flaggedTransaction->status->value ?? null,
            'flagged_by' => $this->flaggedBy?->id,
            'flagged_by_name' => $this->flaggedBy?->username ?? 'System',
            'url' => route('compliance.flags.resolve', $this->flaggedTransaction->id),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'transaction_flagged',
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
            ->where('notification_type', 'transaction_flagged')
            ->first();

        return $preference?->email_enabled ?? true;
    }

    /**
     * Get the notification's database type.
     */
    public function databaseType(User $notifiable): string
    {
        return 'transaction_flagged';
    }
}
