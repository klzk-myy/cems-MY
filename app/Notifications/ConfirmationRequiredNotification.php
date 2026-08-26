<?php

namespace App\Notifications;

use App\Models\TransactionConfirmation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to managers when a transaction confirmation is pending.
 */
class ConfirmationRequiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TransactionConfirmation $confirmation
    ) {}

    public function via(User $notifiable): array
    {
        return ['database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $tx = $this->confirmation->transaction;

        return (new MailMessage)
            ->subject('Transaction Confirmation Required')
            ->line('A transaction requires your confirmation.')
            ->line('Transaction ID: '.($tx->id ?? 'N/A'))
            ->line('Confirmation ID: '.$this->confirmation->id)
            ->action('View Confirmation', url('/'));
    }

    public function toArray(User $notifiable): array
    {
        return [
            'confirmation_id' => $this->confirmation->id,
            'transaction_id' => $this->confirmation->transaction_id,
            'status' => $this->confirmation->status,
            'message' => 'A transaction requires your confirmation.',
        ];
    }
}
