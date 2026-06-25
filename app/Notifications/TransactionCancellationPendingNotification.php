<?php

namespace App\Notifications;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a transaction cancellation is pending supervisor approval.
 */
class TransactionCancellationPendingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Transaction $transaction,
        public User $requestedBy,
        public string $reason
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
        $customer = $this->transaction->customer;

        return (new MailMessage)
            ->subject('Transaction Cancellation Pending Approval - '.config('app.name'))
            ->markdown('emails.transaction-cancellation-pending', [
                'notifiable' => $notifiable,
                'transaction' => $this->transaction,
                'customer' => $customer,
                'requestedBy' => $this->requestedBy,
                'reason' => $this->reason,
                'url' => route('transactions.show', $this->transaction->id),
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
            'type' => 'transaction_cancellation_pending',
            'transaction_id' => $this->transaction->id,
            'customer_id' => $this->transaction->customer_id,
            'customer_name' => $this->transaction->customer?->full_name ?? 'Unknown',
            'amount_local' => $this->transaction->amount_local,
            'currency_code' => $this->transaction->currency_code,
            'reason' => $this->reason,
            'requested_by' => $this->requestedBy->id,
            'requested_by_name' => $this->requestedBy->username ?? 'Unknown',
            'url' => route('transactions.show', $this->transaction->id),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'transaction_cancellation_pending',
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
            ->where('notification_type', 'transaction_cancellation_pending')
            ->first();

        return $preference?->email_enabled ?? true;
    }

    /**
     * Get the notification's database type.
     */
    public function databaseType(User $notifiable): string
    {
        return 'transaction_cancellation_pending';
    }
}
