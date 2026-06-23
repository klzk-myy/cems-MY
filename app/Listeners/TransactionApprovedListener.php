<?php

namespace App\Listeners;

use App\Events\TransactionApproved;
use App\Models\User;
use App\Notifications\TransactionApprovedNotification;
use App\Services\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class TransactionApprovedListener implements ShouldQueue
{
    public $afterCommit = true;

    public function __construct(
        protected AuditService $auditService,
    ) {}

    public function handle(TransactionApproved $event): void
    {
        $transaction = $event->transaction;

        Log::info('TransactionApprovedListener: Processing approval', [
            'transaction_id' => $transaction->id,
        ]);

        $this->notifyTellerAndManager($transaction);
        $this->auditApprovalEvent($transaction);
    }

    protected function notifyTellerAndManager($transaction): void
    {
        $notifiableUsers = collect();

        $teller = User::find($transaction->user_id);
        if ($teller) {
            $notifiableUsers->push($teller);
        }

        $approver = User::find($transaction->approved_by);
        if ($approver && $approver->id !== $teller?->id) {
            $notifiableUsers->push($approver);
        }

        if ($notifiableUsers->isEmpty()) {
            Log::warning('TransactionApprovedListener: No users to notify', [
                'transaction_id' => $transaction->id,
            ]);

            return;
        }

        try {
            Notification::send(
                $notifiableUsers->unique(),
                new TransactionApprovedNotification($transaction)
            );

            Log::info('TransactionApprovedListener: Notifications sent', [
                'transaction_id' => $transaction->id,
                'user_count' => $notifiableUsers->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('TransactionApprovedListener: Failed to send notifications', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function auditApprovalEvent($transaction): void
    {
        try {
            $this->auditService->logWithSeverity(
                'transaction_approved_notification_sent',
                [
                    'user_id' => auth()->id(),
                    'entity_type' => 'Transaction',
                    'entity_id' => $transaction->id,
                    'new_values' => [
                        'transaction_id' => $transaction->id,
                        'amount_local' => $transaction->amount_local,
                        'currency' => $transaction->currency_code,
                        'status' => $transaction->status->value,
                        'approved_by' => $transaction->approved_by,
                    ],
                ],
                'INFO'
            );
        } catch (\Exception $e) {
            Log::error('TransactionApprovedListener: Failed to audit', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
