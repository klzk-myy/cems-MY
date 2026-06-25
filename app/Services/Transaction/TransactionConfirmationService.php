<?php

namespace App\Services\Transaction;

use App\Enums\TransactionConfirmationStatus;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\TransactionConfirmation;
use App\Services\AuditService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionConfirmationService
{
    public function __construct(
        protected AuditService $auditService,
        protected ThresholdService $thresholdService,
        protected MathService $mathService
    ) {}

    /**
     * Determine if a transaction requires manager confirmation.
     *
     * Confirmation is required when the local amount is greater than or equal
     * to the configured structured-transaction threshold.
     */
    public function requiresConfirmation(Transaction $transaction): bool
    {
        $threshold = $this->thresholdService->getStrThreshold();

        return $this->mathService->compare($transaction->amount_local, $threshold) >= 0;
    }

    /**
     * Request confirmation for a large transaction.
     *
     * Creates a new TransactionConfirmation record if one doesn't already exist
     * in pending or confirmed status. Returns the confirmation record.
     *
     * @throws \Exception If creation fails
     */
    public function requestConfirmation(Transaction $transaction, int $userId): TransactionConfirmation
    {
        // Check for existing pending or confirmed confirmation
        $existing = TransactionConfirmation::where('transaction_id', $transaction->id)
            ->whereIn('status', [
                TransactionConfirmationStatus::Pending->value,
                TransactionConfirmationStatus::Confirmed->value,
            ])
            ->first();

        if ($existing) {
            return $existing;
        }

        // Create new confirmation request
        $confirmationToken = bin2hex(random_bytes(32));

        $confirmation = TransactionConfirmation::create([
            'transaction_id' => $transaction->id,
            'user_id' => $userId,
            'status' => TransactionConfirmationStatus::Pending->value,
            'confirmation_token' => $confirmationToken,
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->auditService->logWithSeverity('confirmation_requested', [
            'user_id' => $userId,
            'entity_type' => 'Transaction',
            'entity_id' => $transaction->id,
            'new_values' => [
                'confirmation_id' => $confirmation->id,
                'amount_local' => $transaction->amount_local,
            ],
        ], 'INFO');

        return $confirmation;
    }

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
