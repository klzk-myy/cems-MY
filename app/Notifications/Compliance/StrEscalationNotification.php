<?php

namespace App\Notifications\Compliance;

use App\Models\StrReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class StrEscalationNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public StrReport $strReport
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $daysOverdue = $this->strReport->isOverdue()
            ? abs($this->strReport->daysUntilDeadline())
            : 0;

        return [
            'type' => 'str_escalation',
            'str_id' => $this->strReport->id,
            'str_no' => $this->strReport->str_no,
            'customer_id' => $this->strReport->customer_id,
            'retry_count' => $this->strReport->retry_count,
            'filing_deadline' => $this->strReport->filing_deadline?->toIso8601String(),
            'is_overdue' => $this->strReport->isOverdue(),
            'days_overdue' => $daysOverdue,
            'severity' => $this->strReport->overdueSeverity() ?? 'warning',
            'message' => $this->buildMessage(),
            'url' => "/str/{$this->strReport->id}",
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $daysOverdue = $this->strReport->isOverdue()
            ? abs($this->strReport->daysUntilDeadline())
            : 0;

        return (new MailMessage)
            ->subject("URGENT: STR Submission Escalated - {$this->strReport->str_no}")
            ->greeting("Hello {$notifiable->username},")
            ->line('An STR report has been escalated due to repeated submission failures.')
            ->line('')
            ->line('**STR Details:**')
            ->line("- STR Number: {$this->strReport->str_no}")
            ->line("- Customer ID: {$this->strReport->customer_id}")
            ->line("- Retry Count: {$this->strReport->retry_count}")
            ->line("- Filing Deadline: {$this->strReport->filing_deadline?->format('Y-m-d H:i:s')}")
            ->line('- Status: '.($this->strReport->isOverdue() ? "OVERDUE ({$daysOverdue} days)" : 'Pending'))
            ->line('')
            ->line('**Action Required:**')
            ->line('Please review and manually submit this STR through the compliance portal.')
            ->action('View STR Report', url("/str/{$this->strReport->id}"))
            ->line('')
            ->line('This is an automated escalation. BNM compliance requires timely STR filing.');
    }

    protected function buildMessage(): string
    {
        $strNo = $this->strReport->str_no;
        $retryCount = $this->strReport->retry_count;

        if ($this->strReport->isOverdue()) {
            $daysOverdue = abs($this->strReport->daysUntilDeadline());

            return "[URGENT] STR {$strNo} escalated: {$retryCount} failed attempts, {$daysOverdue} days overdue";
        }

        return "STR {$strNo} escalated: {$retryCount} failed submission attempts — manual intervention required";
    }
}
