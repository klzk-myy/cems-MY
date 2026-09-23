<?php

namespace App\Services\Traits;

use App\Enums\CddLevel;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\TransactionAccountingService;
use App\Services\Audit\AuditTrailHelper;
use Illuminate\Support\Facades\Log;

/**
 * Shared accounting entry creation for transaction services.
 *
 * Removes duplicate createAccountingEntries() methods across:
 * - TransactionCreationService
 * - TransactionApprovalService
 *
 * Consumers MUST provide auditTrailHelper() and
 * transactionAccountingService() accessors (typically backed by
 * constructor-promoted properties). The abstract declarations make the
 * wiring contract explicit — a consumer that forgets it fails at class
 * load, not at first deferred call with an uninitialized property.
 */
trait AccountingEntriesTrait
{
    abstract protected function auditTrailHelper(): AuditTrailHelper;

    abstract protected function transactionAccountingService(): TransactionAccountingService;

    /**
     * Create accounting entries, deferring for Enhanced CDD pending approval.
     *
     * @param  Transaction  $transaction  The transaction to create entries for
     * @param  string|null  $ipAddress  IP address for audit logging
     * @param  User|null  $user  The user performing the action (for audit)
     * @param  bool  $logDeferment  Whether to log deferment (true for creation, false for approval)
     */
    protected function createAccountingEntries(
        Transaction $transaction,
        ?string $ipAddress,
        ?User $user,
        bool $logDeferment = true
    ): void {
        if ($transaction->cdd_level === CddLevel::Enhanced
            && $transaction->status !== TransactionStatus::Completed) {
            if ($logDeferment) {
                $this->logDeferment($transaction, $user, $ipAddress);
            }

            return;
        }

        $this->transactionAccountingService()->createImmediateAccountingEntries($transaction);
    }

    /**
     * Log deferment of journal entry creation.
     */
    protected function logDeferment(Transaction $transaction, ?User $user, ?string $ipAddress): void
    {
        Log::channel('daily')->info('Deferring journal entry creation for Enhanced CDD transaction', [
            'transaction_id' => $transaction->id,
            'status' => $transaction->status->value,
            'cdd_level' => $transaction->cdd_level->value,
        ]);

        $this->auditTrailHelper()->recordTransaction($transaction->id, 'journal_entries_deferred', [
            'cdd_level' => $transaction->cdd_level->value,
            'status' => $transaction->status->value,
            'reason' => 'Enhanced CDD requires approval before bookkeeping',
        ], $user, 'INFO', $ipAddress);
    }
}
