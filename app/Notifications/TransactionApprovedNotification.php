<?php

namespace App\Notifications;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TransactionApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Transaction $transaction
    ) {}

    public function via(User $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if ($this->shouldSendEmail($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(User $notifiable): MailMessage
    {
        $customer = $this->transaction->customer;

        return (new MailMessage)
            ->subject('Transaction Approved - '.config('app.name'))
            ->markdown('emails.transaction-approved', [
                'notifiable' => $notifiable,
                'transaction' => $this->transaction,
                'customer' => $customer,
                'url' => route('transactions.show', $this->transaction->id),
            ]);
    }

    public function toArray(User $notifiable): array
    {
        return [
            'type' => 'transaction_approved',
            'transaction_id' => $this->transaction->id,
            'customer_id' => $this->transaction->customer_id,
            'customer_name' => $this->transaction->customer?->full_name ?? 'Unknown',
            'amount_local' => $this->transaction->amount_local,
            'currency_code' => $this->transaction->currency_code,
            'type' => $this->transaction->type,
            'url' => route('transactions.show', $this->transaction->id),
        ];
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'transaction_approved',
            'data' => $this->toArray($notifiable),
            'created_at' => now()->toIso8601String(),
        ]);
    }

    protected function shouldSendEmail(User $notifiable): bool
    {
        $preference = $notifiable->notificationPreferences()
            ->where('notification_type', 'transaction_approved')
            ->first();

        return $preference?->email_enabled ?? true;
    }

    public function databaseType(User $notifiable): string
    {
        return 'transaction_approved';
    }
}
