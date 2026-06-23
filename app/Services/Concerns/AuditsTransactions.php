<?php

namespace App\Services\Concerns;

use App\Models\SystemLog;

trait AuditsTransactions
{
    /**
     * Log stock transfer events.
     *
     * @param  string  $action  Transfer action (stock_transfer_created,
     *                          stock_transfer_approved_bm, stock_transfer_approved_hq,
     *                          stock_transfer_dispatched, stock_transfer_partially_received,
     *                          stock_transfer_completed, stock_transfer_cancelled,
     *                          stock_transfer_variance_exceeded)
     * @param  int  $transferId  Stock transfer ID
     * @param  array  $data  Transfer data with old/new values
     */
    public function logStockTransferEvent(string $action, int $transferId, array $data = []): SystemLog
    {
        $severity = match ($action) {
            'stock_transfer_partially_received', 'stock_transfer_cancelled',
            'stock_transfer_variance_exceeded' => 'WARNING',
            default => 'INFO',
        };

        return $this->logWithSeverity(
            $action,
            [
                'entity_type' => 'StockTransfer',
                'entity_id' => $transferId,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log journal entry workflow events.
     *
     * @param  string  $action  Workflow action (journal_entry_submitted,
     *                          journal_entry_approved, journal_entry_rejected)
     * @param  int  $entryId  Journal entry ID
     * @param  array  $data  Workflow data
     */
    public function logJournalWorkflowEvent(string $action, int $entryId, array $data = []): SystemLog
    {
        $severity = $action === 'journal_entry_rejected' ? 'WARNING' : 'INFO';

        return $this->logWithSeverity(
            $action,
            [
                'entity_type' => 'JournalEntry',
                'entity_id' => $entryId,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log currency position events.
     *
     * @param  string  $action  Position action (position_revaluation_run,
     *                          position_limit_breach, position_manual_adjustment)
     * @param  array  $data  Position data with old/new values
     */
    public function logPositionEvent(string $action, array $data = []): SystemLog
    {
        $severity = match ($action) {
            'position_limit_breach', 'position_manual_adjustment' => 'WARNING',
            default => 'INFO',
        };

        return $this->logWithSeverity(
            $action,
            [
                'entity_type' => 'CurrencyPosition',
                'entity_id' => $data['position_id'] ?? null,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    public function logTransactionWorkflow(
        string $step,
        int $transactionId,
        string $status,
        array $context = []
    ): SystemLog {
        return $this->logWithSeverity(
            $step,
            [
                'entity_type' => 'Transaction',
                'entity_id' => $transactionId,
                'new_values' => $context,
            ],
            $status === 'ERROR' ? 'ERROR' : 'INFO'
        );
    }
}
