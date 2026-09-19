<?php

namespace App\Notifications;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TransactionOutcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Transaction $transaction,
        public string $outcome,
        public string $actorName,
        public ?string $reason = null
    ) {
        $this->outcome = strtolower($outcome);
    }

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $approved = $this->outcome === 'approved';

        $mail = (new MailMessage)
            ->subject(
                ($approved ? 'Transaction Approved' : 'Transaction Rejected').' - '.config('app.name')
            )
            ->greeting("Hello {$notifiable->username},")
            ->line($this->summaryLine())
            ->line('Reference: '.$this->transaction->reference)
            ->line('Amount: '.$this->transaction->amount_myr.' '.$this->transaction->currency_code)
            ->line('Actioned by: '.$this->actorName);

        if ($this->reason) {
            $mail->line('Reason: '.$this->reason);
        }

        return $mail->action('View Transaction', route('transactions.show', $this->transaction->id));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'type' => 'transaction_outcome',
            'outcome' => $this->outcome,
            'transaction_id' => $this->transaction->id,
            'transaction_reference' => $this->transaction->reference,
            'amount_myr' => $this->transaction->amount_myr,
            'currency_code' => $this->transaction->currency_code,
            'actor_name' => $this->actorName,
            'reason' => $this->reason,
            // 'type' is the notification discriminator consumed by
            // NotificationBadgeService - the transaction's Buy/Sell kind goes
            // under transaction_type so it cannot overwrite it.
            'transaction_type' => $this->transaction->type,
            'message' => $this->summaryLine(),
            'url' => route('transactions.show', $this->transaction->id),
        ];
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'transaction_outcome',
            'data' => $this->toArray($notifiable),
            'created_at' => now()->toIso8601String(),
        ]);
    }

    public function databaseType(User $notifiable): string
    {
        return 'transaction_outcome';
    }

    private function summaryLine(): string
    {
        return $this->outcome === 'approved'
            ? "Your transaction {$this->transaction->reference} was approved by {$this->actorName}."
            : "Your transaction {$this->transaction->reference} was rejected by {$this->actorName}.";
    }
}
