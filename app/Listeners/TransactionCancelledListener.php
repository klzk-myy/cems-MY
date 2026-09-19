<?php

namespace App\Listeners;

use App\Events\TransactionCancelled;
use App\Services\AuditService;
use Illuminate\Support\Facades\Log;

class TransactionCancelledListener
{
    public function __construct(
        protected AuditService $auditService,
    ) {}

    public function __invoke(TransactionCancelled $event): void
    {
        Log::info('Transaction cancelled', [
            'transaction_id' => $event->transaction->id,
            'reason' => $event->reason,
            'cancelled_by' => $event->cancelledBy,
        ]);

        // Create audit trail entry per BNM requirements
        $this->auditService->logWithSeverity(
            'transaction_cancelled',
            [
                'entity_type' => 'Transaction',
                'entity_id' => $event->transaction->id,
                'user_id' => $event->cancelledBy,
                'new_values' => [
                    'transaction_id' => $event->transaction->id,
                    'amount_myr' => $event->transaction->amount_myr,
                    'currency' => $event->transaction->currency_code,
                    'status' => $event->transaction->status->value,
                    'cancellation_reason' => $event->reason,
                ],
            ],
            'INFO'
        );
    }
}
