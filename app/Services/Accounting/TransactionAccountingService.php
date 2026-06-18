<?php

namespace App\Services\Accounting;

use App\Enums\AccountCode;
use App\Enums\CddLevel;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\AuditService;
use App\Services\MathService;
use App\Services\TransactionService;
use Illuminate\Support\Facades\Log;

/**
 * Transaction Accounting Service
 *
 * Handles accounting entry creation for transactions.
 * Extracted from TransactionService to reduce its size and improve maintainability.
 */
class TransactionAccountingService
{
    public function __construct(
        protected AccountingService $accountingService,
        protected CurrencyPositionService $positionService,
        protected MathService $mathService,
        protected AuditService $auditService,
    ) {}

    /**
     * Create deferred journal entries for Enhanced CDD transactions.
     * Called when transaction is approved.
     */
    public function createDeferredAccountingEntries(int $transactionId): void
    {
        $transaction = Transaction::findOrFail($transactionId);

        // Verify it's Enhanced CDD
        if ($transaction->cdd_level !== CddLevel::Enhanced) {
            throw new \InvalidArgumentException('Only Enhanced CDD transactions support deferred entries');
        }

        // Verify it's completed (approved)
        if ($transaction->status !== TransactionStatus::Completed) {
            throw new \InvalidArgumentException('Transaction must be completed to create journal entries');
        }

        // Verify entries weren't already created
        if ($transaction->journal_entry_id !== null) {
            Log::info('Journal entries already exist for transaction', [
                'transaction_id' => $transactionId,
                'journal_entry_id' => $transaction->journal_entry_id,
            ]);

            return;
        }

        // Create the entries
        $this->createImmediateAccountingEntries($transaction);

        // Mark as having deferred accounting (Enhanced CDD was deferred until approval)
        $transaction->has_deferred_accounting = true;
        $transaction->save();

        $this->auditService->logTransaction('deferred_journal_entries_created', $transaction->id, [
            'transaction_id' => $transaction->id,
            'journal_entry_id' => $transaction->journal_entry_id,
            'deferred_until' => now(),
            'approver_id' => $transaction->approved_by,
        ]);
    }

    /**
     * Create accounting journal entries immediately.
     */
    public function createImmediateAccountingEntries(Transaction $transaction): void
    {
        $entries = [];

        if ($transaction->type->isBuy()) {
            $entries = [
                [
                    'account_code' => AccountCode::FOREIGN_CURRENCY_INVENTORY->value,
                    'debit' => $transaction->amount_local,
                    'credit' => '0',
                    'description' => "Buy {$transaction->amount_foreign} {$transaction->currency_code} @ {$transaction->rate}",
                ],
                [
                    'account_code' => AccountCode::CASH_MYR->value,
                    'debit' => '0',
                    'credit' => $transaction->amount_local,
                    'description' => "Payment for {$transaction->currency_code} purchase",
                ],
            ];
        } else {
            $position = $this->positionService->getPosition($transaction->currency_code, $transaction->till_id);
            $avgCost = $position ? $position->avg_cost_rate : $transaction->rate;

            if ($avgCost === null) {
                throw new \RuntimeException('Cannot calculate cost basis: no position or rate available for transaction');
            }

            $costBasis = $this->mathService->multiply((string) $transaction->amount_foreign, $avgCost);
            $revenue = $this->mathService->subtract((string) $transaction->amount_local, $costBasis);
            $isGain = $this->mathService->compare($revenue, '0') >= 0;

            $entries = [
                [
                    'account_code' => AccountCode::CASH_MYR->value,
                    'debit' => $transaction->amount_local,
                    'credit' => '0',
                    'description' => "Sale of {$transaction->amount_foreign} {$transaction->currency_code}",
                ],
                [
                    'account_code' => AccountCode::FOREIGN_CURRENCY_INVENTORY->value,
                    'debit' => '0',
                    'credit' => $costBasis,
                    'description' => "Cost of {$transaction->currency_code} sold",
                ],
            ];

            if ($isGain) {
                $entries[] = [
                    'account_code' => AccountCode::FOREX_TRADING_REVENUE->value,
                    'debit' => '0',
                    'credit' => $revenue,
                    'description' => "Gain on {$transaction->currency_code} sale",
                ];
            } else {
                $entries[] = [
                    'account_code' => AccountCode::FOREX_LOSS->value,
                    'debit' => $this->mathService->multiply($revenue, '-1'),
                    'credit' => '0',
                    'description' => "Loss on {$transaction->currency_code} sale",
                ];
            }
        }

        $journalEntry = $this->accountingService->createJournalEntry(
            $entries,
            'Transaction',
            $transaction->id,
            "Transaction #{$transaction->id} - {$transaction->type->value} {$transaction->currency_code}"
        );

        // Link journal entry to transaction
        $transaction->journal_entry_id = $journalEntry->id;
        $transaction->journal_entries_created_at = now();
        $transaction->has_deferred_accounting = false;
        $transaction->save();
    }
}
