<?php

namespace App\Notifications;

use App\Models\Compliance\ComplianceCase;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to a case assignee when their case passes its SLA deadline.
 */
class ComplianceCaseSlaBreachedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ComplianceCase $complianceCase,
        public int $hoursOverdue,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'case_id' => $this->complianceCase->id,
            'case_number' => $this->complianceCase->case_number,
            'priority' => $this->complianceCase->priority->value,
            'sla_deadline' => $this->complianceCase->sla_deadline->toIso8601String(),
            'hours_overdue' => $this->hoursOverdue,
            'type' => 'case_sla_breached',
        ];
    }
}
