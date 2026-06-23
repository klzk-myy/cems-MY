<?php

namespace App\Notifications;

use App\Models\EmergencyClosure;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmergencyCounterClosureNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public EmergencyClosure $closure
    ) {}

    public function via(User $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('EMERGENCY: Counter '.$this->closure->counter->code.' Closed')
            ->line('An emergency counter closure was triggered.')
            ->line('Teller: '.$this->closure->teller->username)
            ->line('Reason: '.$this->closure->reason)
            ->line('Time: '.$this->closure->closed_at->toDateTimeString())
            ->action('Review Closure', url('/counters/'.$this->closure->counter->code.'/emergency/'.$this->closure->id));
    }

    public function toArray(User $notifiable): array
    {
        return [
            'type' => 'emergency_counter_close',
            'closure_id' => $this->closure->id,
            'counter_code' => $this->closure->counter->code,
            'teller_id' => $this->closure->teller_id,
            'teller_name' => $this->closure->teller->username,
            'reason' => $this->closure->reason,
            'closed_at' => $this->closure->closed_at->toIso8601String(),
        ];
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'emergency_counter_close',
            'data' => $this->toArray($notifiable),
            'created_at' => now()->toIso8601String(),
        ]);
    }
}
