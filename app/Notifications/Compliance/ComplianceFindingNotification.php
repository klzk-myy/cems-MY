<?php

namespace App\Notifications\Compliance;

use App\Models\Compliance\ComplianceFinding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
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
        return ['database'];
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
        $severity = $this->finding->severity->value;

        return "[{$severity}] {$type} finding detected — requires review";
    }
}
