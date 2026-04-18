<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Deferred Accounting Reconciliation Failed Notification
 *
 * Notifies compliance officers when the reconciliation job cannot auto-reconcile
 * certain transactions and requires manual intervention.
 */
class DeferredAccountingReconciliationFailedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The reconciliation report containing failed items
     */
    public array $report;

    /**
     * Create a new notification instance.
     *
     * @param  array  $report  The reconciliation report with cannot_reconcile details
     */
    public function __construct(array $report)
    {
        $this->report = $report;
    }

    /**
     * Get the notification channels.
     *
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail subject.
     */
    public function subject(): string
    {
        return 'CRITICAL: Deferred Accounting Reconciliation Failed';
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        $mail = (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject($this->subject())
            ->error()
            ->line('## Deferred Accounting Reconciliation Failed')
            ->line("The EOD reconciliation job found **{$this->report['cannot_reconcile_count']} transaction(s)** that could not be auto-reconciled.")
            ->line('')
            ->line('### Transactions Requiring Manual Intervention:');

        foreach ($this->report['cannot_reconcile'] as $item) {
            $mail->line("- **Transaction #{$item['transaction_id']}**: {$item['currency']} {$item['amount_local']} - Reason: {$item['reason']}");
        }

        $mail->line('')
            ->line('### Summary:')
            ->line("- Fixed: {$this->report['fixed_count']} transactions (Total: RM {$this->report['total_amount_fixed']})")
            ->line("- Still Missing: {$this->report['still_missing_count']} transactions")
            ->line("- Cannot Reconcile: {$this->report['cannot_reconcile_count']} transactions")
            ->line('')
            ->line('Please review these transactions in the system and create the journal entries manually.');

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'deferred_accounting_reconciliation_failed',
            'fixed_count' => $this->report['fixed_count'],
            'still_missing_count' => $this->report['still_missing_count'],
            'cannot_reconcile_count' => $this->report['cannot_reconcile_count'],
            'total_amount_fixed' => $this->report['total_amount_fixed'],
            'cannot_reconcile' => $this->report['cannot_reconcile'],
            'message' => "{$this->report['cannot_reconcile_count']} transaction(s) could not be auto-reconciled",
            'url' => '/accounting/journal-entries?filter=deferred',
            'created_at' => now()->toIso8601String(),
        ];
    }
}
