<?php

namespace App\Notifications;

use App\Models\Compliance\ComplianceCase;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a compliance case is assigned to an officer.
 */
class ComplianceCaseAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public ComplianceCase $complianceCase,
        public ?User $assignedBy = null
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
        $customer = $this->complianceCase->customer;
        $slaDeadline = $this->complianceCase->sla_deadline;

        return (new MailMessage)
            ->subject('Compliance Case Assigned - '.config('app.name'))
            ->markdown('emails.compliance-case-assigned', [
                'notifiable' => $notifiable,
                'complianceCase' => $this->complianceCase,
                'customer' => $customer,
                'assignedBy' => $this->assignedBy,
                'caseType' => $this->complianceCase->case_type?->label() ?? 'Unknown',
                'priority' => $this->complianceCase->priority?->label() ?? 'Unknown',
                'severity' => $this->complianceCase->severity?->label() ?? 'Unknown',
                'slaDeadline' => $slaDeadline,
                'daysUntilDeadline' => $slaDeadline ? now()->diffInDays($slaDeadline, false) : null,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        $slaDeadline = $this->complianceCase->sla_deadline;

        return [
            'type' => 'compliance_case_assigned',
            'case_id' => $this->complianceCase->id,
            'case_number' => $this->complianceCase->case_number,
            'case_type' => $this->complianceCase->case_type?->value ?? null,
            'case_type_label' => $this->complianceCase->case_type?->label() ?? 'Unknown',
            'customer_id' => $this->complianceCase->customer_id,
            'customer_name' => $this->complianceCase->customer?->full_name ?? 'Unknown',
            'priority' => $this->complianceCase->priority?->value ?? null,
            'severity' => $this->complianceCase->severity?->value ?? null,
            'sla_deadline' => $slaDeadline?->toIso8601String(),
            'days_until_deadline' => $slaDeadline ? now()->diffInDays($slaDeadline, false) : null,
            'assigned_by' => $this->assignedBy?->id,
            'assigned_by_name' => $this->assignedBy?->username ?? 'System',
            'url' => route('compliance.cases.show', $this->complianceCase->id),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'compliance_case_assigned',
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
            ->where('notification_type', 'compliance_case_assigned')
            ->first();

        return $preference?->email_enabled ?? true;
    }

    /**
     * Get the notification's database type.
     */
    public function databaseType(User $notifiable): string
    {
        return 'compliance_case_assigned';
    }
}
