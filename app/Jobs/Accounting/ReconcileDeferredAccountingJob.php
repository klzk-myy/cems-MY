<?php

namespace App\Jobs\Accounting;

use App\Enums\CddLevel;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\DeferredAccountingReconciliationFailedNotification;
use App\Services\AuditService;
use App\Services\MathService;
use App\Services\TransactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Reconcile Deferred Accounting Entries Job
 *
 * Finds transactions that are completed but missing journal entries (deferred accounting
 * wasn't created for Enhanced CDD transactions) and auto-creates them.
 *
 * This job is idempotent - it can be run multiple times safely without creating
 * duplicate entries or causing issues.
 *
 * Run at EOD reconciliation time after all transactions are completed.
 */
class ReconcileDeferredAccountingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of retry attempts
     */
    public int $tries = 3;

    /**
     * Job timeout in seconds
     */
    public int $timeout = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected MathService $mathService,
        protected TransactionService $transactionService,
        protected AuditService $auditService,
    ) {}

    /**
     * Execute the job.
     *
     * Finds all Enhanced CDD transactions that are completed but missing journal entries,
     * and creates the deferred accounting entries for them.
     */
    public function handle(): void
    {
        Log::info('ReconcileDeferredAccountingJob started');

        $report = $this->reconcile();

        Log::info('ReconcileDeferredAccountingJob completed', [
            'fixed_count' => $report['fixed_count'],
            'still_missing_count' => $report['still_missing_count'],
            'total_amount_fixed' => $report['total_amount_fixed'],
            'cannot_reconcile_count' => $report['cannot_reconcile_count'],
        ]);

        // If any cannot be auto-reconciled, alert the compliance team
        if ($report['cannot_reconcile_count'] > 0) {
            $this->alertComplianceTeam($report);
        }
    }

    /**
     * Perform the reconciliation.
     *
     * @return array{fixed_count: int, still_missing_count: int, total_amount_fixed: string, cannot_reconcile_count: int, cannot_reconcile: array, fixed_transactions: array, still_missing: array}
     */
    protected function reconcile(): array
    {
        $report = [
            'fixed_count' => 0,
            'still_missing_count' => 0,
            'total_amount_fixed' => '0',
            'cannot_reconcile_count' => 0,
            'cannot_reconcile' => [],
            'fixed_transactions' => [],
            'still_missing' => [],
        ];

        // Find transactions eligible for reconciliation:
        // 1. status = 'Completed'
        // 2. cdd_level = 'Enhanced'
        // 3. journal_entry_id IS NULL (entries not yet created)
        // OR has_deferred_accounting = true but journal_entry_id = null
        $transactions = Transaction::where('status', TransactionStatus::Completed)
            ->where('cdd_level', CddLevel::Enhanced)
            ->where(function ($query) {
                $query->whereNull('journal_entry_id')
                    ->orWhere(function ($q) {
                        $q->where('has_deferred_accounting', true)
                            ->whereNull('journal_entry_id');
                    });
            })
            ->get();

        Log::info('ReconcileDeferredAccountingJob found transactions to check', [
            'transaction_count' => $transactions->count(),
        ]);

        foreach ($transactions as $transaction) {
            $reconciliationResult = $this->reconcileTransaction($transaction);

            if ($reconciliationResult['success']) {
                $report['fixed_count']++;
                $report['total_amount_fixed'] = $this->mathService->add(
                    $report['total_amount_fixed'],
                    (string) $transaction->amount_local
                );
                $report['fixed_transactions'][] = [
                    'transaction_id' => $transaction->id,
                    'amount_local' => (string) $transaction->amount_local,
                    'currency' => $transaction->currency_code,
                ];
            } elseif ($reconciliationResult['can_reconcile'] === false) {
                $report['cannot_reconcile_count']++;
                $report['cannot_reconcile'][] = [
                    'transaction_id' => $transaction->id,
                    'amount_local' => (string) $transaction->amount_local,
                    'currency' => $transaction->currency_code,
                    'reason' => $reconciliationResult['reason'],
                ];
            } else {
                $report['still_missing_count']++;
                $report['still_missing'][] = [
                    'transaction_id' => $transaction->id,
                    'amount_local' => (string) $transaction->amount_local,
                    'currency' => $transaction->currency_code,
                    'reason' => $reconciliationResult['reason'],
                ];
            }
        }

        return $report;
    }

    /**
     * Reconcile a single transaction.
     *
     * @return array{success: bool, can_reconcile: bool|null, reason: string}
     */
    protected function reconcileTransaction(Transaction $transaction): array
    {
        // Idempotency check: if journal_entry_id is now set, skip
        // (another process may have created it after we fetched)
        $transaction->refresh();

        if ($transaction->journal_entry_id !== null) {
            return [
                'success' => false,
                'can_reconcile' => null,
                'reason' => 'Journal entry already created by another process',
            ];
        }

        try {
            // Call the TransactionService to create deferred accounting entries
            $this->transactionService->createDeferredAccountingEntries($transaction->id);

            // Audit log for the reconciliation
            $this->auditService->logWithSeverity(
                'deferred_accounting_reconciled',
                [
                    'entity_type' => 'Transaction',
                    'entity_id' => $transaction->id,
                    'new_values' => [
                        'transaction_id' => $transaction->id,
                        'amount_local' => (string) $transaction->amount_local,
                        'currency' => $transaction->currency_code,
                        'cdd_level' => $transaction->cdd_level->value,
                        'reconciled_at' => now()->toIso8601String(),
                    ],
                ],
                'INFO'
            );

            return [
                'success' => true,
                'can_reconcile' => true,
                'reason' => '',
            ];
        } catch (\InvalidArgumentException $e) {
            // Expected when transaction doesn't support deferred entries or not in correct state
            return [
                'success' => false,
                'can_reconcile' => false,
                'reason' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            // Unexpected error - log but don't mark as auto-reconcilable
            Log::error('ReconcileDeferredAccountingJob: Unexpected error reconciling transaction', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            $this->auditService->logWithSeverity(
                'deferred_accounting_reconciliation_error',
                [
                    'entity_type' => 'Transaction',
                    'entity_id' => $transaction->id,
                    'new_values' => [
                        'error' => $e->getMessage(),
                        'requires_manual_review' => true,
                    ],
                ],
                'ERROR'
            );

            return [
                'success' => false,
                'can_reconcile' => false,
                'reason' => 'Unexpected error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Alert the compliance team about transactions that cannot be auto-reconciled.
     *
     * @param  array  $report  The reconciliation report
     */
    protected function alertComplianceTeam(array $report): void
    {
        // Find compliance officers to notify
        $complianceOfficers = User::where('role', UserRole::ComplianceOfficer)->get();

        if ($complianceOfficers->isEmpty()) {
            Log::warning('ReconcileDeferredAccountingJob: No compliance officers found for notification');

            $this->auditService->logWithSeverity(
                'deferred_accounting_reconciliation_alert_failed',
                [
                    'entity_type' => 'ReconciliationReport',
                    'new_values' => [
                        'reason' => 'No compliance officers found',
                        'transactions_affected' => $report['cannot_reconcile_count'],
                    ],
                ],
                'CRITICAL'
            );

            return;
        }

        try {
            Notification::send(
                $complianceOfficers,
                new DeferredAccountingReconciliationFailedNotification($report)
            );

            $this->auditService->logWithSeverity(
                'deferred_accounting_reconciliation_alert_sent',
                [
                    'entity_type' => 'ReconciliationReport',
                    'new_values' => [
                        'compliance_officers_notified' => $complianceOfficers->count(),
                        'transactions_affected' => $report['cannot_reconcile_count'],
                    ],
                ],
                'CRITICAL'
            );

            Log::critical('ReconcileDeferredAccountingJob: Alerted compliance team', [
                'compliance_officers_notified' => $complianceOfficers->count(),
                'transactions_affected' => $report['cannot_reconcile_count'],
            ]);
        } catch (\Exception $e) {
            Log::error('ReconcileDeferredAccountingJob: Failed to alert compliance team', [
                'error' => $e->getMessage(),
            ]);

            $this->auditService->logWithSeverity(
                'deferred_accounting_reconciliation_alert_failed',
                [
                    'entity_type' => 'ReconciliationReport',
                    'new_values' => [
                        'reason' => $e->getMessage(),
                        'transactions_affected' => $report['cannot_reconcile_count'],
                    ],
                ],
                'CRITICAL'
            );
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('ReconcileDeferredAccountingJob permanently failed', [
            'exception' => $exception->getMessage(),
        ]);

        $this->auditService->logWithSeverity(
            'deferred_accounting_reconciliation_job_failed',
            [
                'entity_type' => 'Job',
                'new_values' => [
                    'job' => static::class,
                    'exception' => $exception->getMessage(),
                ],
            ],
            'CRITICAL'
        );
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'accounting',
            'deferred-accounting',
            'reconciliation',
        ];
    }
}
