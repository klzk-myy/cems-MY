<?php

namespace App\Notifications;

use App\Models\StockReservation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public StockReservation $reservation
    ) {}

    public function via(User $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $transaction = $this->reservation->transaction;

        return (new MailMessage)
            ->subject('Stock Reservation Expired - '.config('app.name'))
            ->line('Your stock reservation has expired and been released.')
            ->line('Transaction ID: '.($transaction?->id ?? 'N/A'))
            ->line('Currency: '.$this->reservation->currency_code)
            ->line('Amount: '.$this->reservation->amount_foreign)
            ->line('Please contact your manager if you have questions.');
    }

    public function toArray(User $notifiable): array
    {
        $transaction = $this->reservation->transaction;

        return [
            'type' => 'reservation_expired',
            'reservation_id' => $this->reservation->id,
            'transaction_id' => $transaction?->id,
            'currency_code' => $this->reservation->currency_code,
            'amount_foreign' => $this->reservation->amount_foreign,
        ];
    }
}
