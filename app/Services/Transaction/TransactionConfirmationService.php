<?php

namespace App\Services\Transaction;

use App\Enums\TransactionStatus;
use App\Models\TransactionConfirmation;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionConfirmationService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Confirm or reject a transaction confirmation.
     *
     * @param  array  $validated  Must contain 'confirmation_action' => 'confirm'|'reject' and optional 'notes'
     * @return array{success: bool, message: string}
     */
    public function confirm(TransactionConfirmation $confirmation, array $validated, int $userId): array
    {
        if ($confirmation->isExpired()) {
            $confirmation->markExpired();

            return [
                'success' => false,
                'message' => 'Confirmation has expired. Please request a new confirmation.',
            ];
        }

        $action = $validated['confirmation_action'];
        $notes = $validated['notes'] ?? null;

        DB::beginTransaction();
        try {
            if ($action === 'confirm') {
                return $this->handleConfirm($confirmation, $userId, $notes);
            } else {
                return $this->handleReject($confirmation, $userId, $notes);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Transaction confirmation failed', [
                'confirmation_id' => $confirmation->id,
                'transaction_id' => $confirmation->transaction_id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle confirmation action.
     */
    protected function handleConfirm(TransactionConfirmation $confirmation, int $userId, ?string $notes): array
    {
        $confirmation->markConfirmed($userId, $notes);

        // Refresh transaction for any downstream listeners (if needed)
        $confirmation->transaction->refresh();

        $this->auditService->logWithSeverity('transaction_confirmed', [
            'user_id' => $userId,
            'entity_type' => 'Transaction',
            'entity_id' => $confirmation->transaction_id,
            'new_values' => [
                'confirmation_id' => $confirmation->id,
                'confirmed_by' => $userId,
            ],
        ], 'INFO');

        DB::commit();

        return [
            'success' => true,
            'message' => 'Transaction confirmed and pending final approval.',
        ];
    }

    /**
     * Handle rejection action.
     */
    protected function handleReject(TransactionConfirmation $confirmation, int $userId, ?string $notes): array
    {
        $confirmation->markRejected($userId, $notes);

        $confirmation->transaction->update([
            'status' => TransactionStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => $userId,
            'cancellation_reason' => 'Rejected during confirmation: '.($notes ?? 'No reason provided'),
        ]);

        $this->auditService->logWithSeverity('transaction_rejected', [
            'user_id' => $userId,
            'entity_type' => 'Transaction',
            'entity_id' => $confirmation->transaction_id,
            'new_values' => [
                'confirmation_id' => $confirmation->id,
                'rejected_by' => $userId,
                'reason' => $notes ?? 'No reason provided',
            ],
        ], 'WARNING');

        DB::commit();

        return [
            'success' => true,
            'message' => 'Transaction has been rejected.',
        ];
    }
}
