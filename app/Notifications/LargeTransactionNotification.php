<?php

namespace App\Notifications;

use App\Models\Transaction;
use App\Models\TransactionConfirmation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent for large transactions requiring manager approval.
 */
class LargeTransactionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Transaction $transaction,
        public TransactionConfirmation $confirmation
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
        $amount = $this->formatAmount();

        return (new MailMessage)
            ->subject('Large Transaction Requires Approval - '.config('app.name'))
            ->markdown('emails.large-transaction', [
                'notifiable' => $notifiable,
                'transaction' => $this->transaction,
                'customer' => $customer,
                'confirmation' => $this->confirmation,
                'amount' => $amount,
                'transactionType' => $this->transaction->transaction_type?->label() ?? 'Unknown',
                'currency' => $this->transaction->currency?->code ?? 'Unknown',
                'branch' => $this->transaction->branch?->name ?? 'Unknown',
                'teller' => $this->transaction->teller,
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
            'type' => 'large_transaction',
            'transaction_id' => $this->transaction->id,
            'confirmation_id' => $this->confirmation->id,
            'customer_id' => $this->transaction->customer_id,
            'customer_name' => $this->transaction->customer?->full_name ?? 'Unknown',
            'amount' => $this->transaction->amount,
            'amount_formatted' => $this->formatAmount(),
            'currency_code' => $this->transaction->currency?->code ?? null,
            'transaction_type' => $this->transaction->transaction_type?->value ?? null,
            'branch_id' => $this->transaction->branch_id,
            'branch_name' => $this->transaction->branch?->name ?? 'Unknown',
            'teller_id' => $this->transaction->created_by,
            'created_at' => $this->transaction->created_at->toIso8601String(),
            'requires_approval' => true,
            'url' => route('transactions.confirm', $this->confirmation->id),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'large_transaction',
            'data' => $this->toArray($notifiable),
            'created_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Format the transaction amount for display.
     * Uses string-safe formatting to avoid float precision loss on large amounts.
     */
    protected function formatAmount(): string
    {
        $amount = (string) $this->transaction->amount;
        $currency = $this->transaction->currency?->code ?? 'MYR';

        // Split into integer and decimal parts to avoid float cast precision loss
        $parts = explode('.', $amount, 2);
        $intPart = $parts[0] ?? '0';
        $decPart = isset($parts[1]) ? str_pad(substr($parts[1], 0, 2), 2, '0') : '00';

        // Add thousands separator using string manipulation for precision safety
        $intFormatted = '';
        $len = strlen($intPart);
        for ($i = 0; $i < $len; $i++) {
            if ($i > 0 && ($len - $i) % 3 === 0) {
                $intFormatted .= ',';
            }
            $intFormatted .= $intPart[$i];
        }

        return $intFormatted.'.'.$decPart.' '.$currency;
    }

    /**
     * Determine if email should be sent based on user preferences.
     * Caches preferences per user to avoid N+1 queries in batch scenarios.
     */
    protected function shouldSendEmail(User $notifiable): bool
    {
        static $preferences = [];

        $userId = $notifiable->id;

        if (! array_key_exists($userId, $preferences)) {
            $preference = $notifiable->notificationPreferences()
                ->where('notification_type', 'large_transaction')
                ->first();

            $preferences[$userId] = $preference?->email_enabled ?? true;
        }

        return $preferences[$userId];
    }

    /**
     * Get the notification's database type.
     */
    public function databaseType(User $notifiable): string
    {
        return 'large_transaction';
    }
}
