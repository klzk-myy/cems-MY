<?php

namespace App\Notifications\Compliance;

use App\Models\Compliance\ComplianceFinding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class ComplianceFindingNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ComplianceFinding $finding
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'compliance_finding',
            'finding_id' => $this->finding->id,
            'finding_type' => $this->finding->finding_type->value,
            'severity' => $this->finding->severity->value,
            'subject_type' => $this->finding->subject_type,
            'subject_id' => $this->finding->subject_id,
            'message' => $this->buildMessage(),
            'url' => "/compliance/findings/{$this->finding->id}",
            'created_at' => $this->finding->generated_at->toIso8601String(),
        ];
    }

    protected function buildMessage(): string
    {
        $type = $this->finding->finding_type->label();
        $severity = $this->finding->severity->label();

        return "[{$severity}] {$type} finding detected — requires review";
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $findingType = $this->finding->finding_type->label();
        $severity = $this->finding->severity->label();
        $subjectInfo = $this->finding->subject;
        $url = "/compliance/findings/{$this->finding->id}";

        return (new MailMessage)
            ->subject("[{$severity}] Compliance Finding: {$findingType}")
            ->markdown('emails.compliance-finding', [
                'finding' => $this->finding,
                'findingType' => $findingType,
                'severity' => $severity,
                'subjectInfo' => $subjectInfo,
                'url' => $url,
                'generatedAt' => $this->finding->generated_at,
                'details' => $this->finding->details ?? [],
            ]);
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'compliance_finding',
            'data' => $this->toArray($notifiable),
            'created_at' => $this->finding->created_at->toIso8601String(),
        ]);
    }

    /**
     * Get the notification's database type.
     */
    public function databaseType(object $notifiable): string
    {
        return 'compliance_finding';
    }
}
