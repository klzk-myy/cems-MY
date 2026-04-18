<?php

namespace App\Notifications;

use App\Models\SanctionEntry;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a sanctions match is detected.
 */
class SanctionsMatchNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public SanctionEntry $sanctionEntry,
        public ?string $matchReason = null
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        // Always send email for sanctions matches - critical compliance issue
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
        $sanctionList = $this->sanctionEntry->sanctionList;
        $matchType = $this->sanctionEntry->match_type;

        return (new MailMessage)
            ->error()
            ->subject('[URGENT] Sanctions Match Detected - '.config('app.name'))
            ->markdown('emails.sanctions-match', [
                'notifiable' => $notifiable,
                'sanctionEntry' => $this->sanctionEntry,
                'sanctionList' => $sanctionList,
                'matchType' => $matchType?->label() ?? 'Unknown',
                'matchReason' => $this->matchReason,
                'screenedName' => $this->sanctionEntry->screened_name,
                'matchedName' => $this->sanctionEntry->matched_name,
                'matchScore' => $this->sanctionEntry->match_score,
                'isWhitelisted' => $this->sanctionEntry->is_whitelisted,
                'createdAt' => $this->sanctionEntry->created_at,
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
            'type' => 'sanctions_match',
            'sanction_entry_id' => $this->sanctionEntry->id,
            'sanction_list_id' => $this->sanctionEntry->sanction_list_id,
            'sanction_list_name' => $this->sanctionEntry->sanctionList?->name ?? 'Unknown',
            'customer_id' => $this->sanctionEntry->customer_id,
            'customer_name' => $this->sanctionEntry->customer?->full_name ?? 'Unknown',
            'transaction_id' => $this->sanctionEntry->transaction_id,
            'match_type' => $this->sanctionEntry->match_type?->value ?? null,
            'match_type_label' => $this->sanctionEntry->match_type?->label() ?? 'Unknown',
            'screened_name' => $this->sanctionEntry->screened_name,
            'matched_name' => $this->sanctionEntry->matched_name,
            'match_score' => $this->sanctionEntry->match_score,
            'is_whitelisted' => $this->sanctionEntry->is_whitelisted,
            'match_reason' => $this->matchReason,
            'url' => route('compliance.sanctions.show', $this->sanctionEntry->id),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'sanctions_match',
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
            ->where('notification_type', 'sanctions_match')
            ->first();

        // Sanctions matches are critical - default to true
        return $preference?->email_enabled ?? true;
    }

    /**
     * Get the notification's database type.
     */
    public function databaseType(User $notifiable): string
    {
        return 'sanctions_match';
    }
}
