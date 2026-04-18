<?php

namespace App\Services;

use App\Enums\AccountCode;
use App\Enums\CddLevel;
use App\Enums\ComplianceFlagType;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Events\TransactionApproved;
use App\Events\TransactionCreated;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\StockReservationExpiredException;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Models\ApprovalTask;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Transaction Service
 *
 * Handles core transaction creation logic for both web and API controllers.
 * Ensures BCMath precision for all monetary calculations and compliance checks.
 */
class TransactionService
{
    public function __construct(
        protected MathService $mathService,
        protected ComplianceService $complianceService,
        protected CurrencyPositionService $positionService,
        protected AccountingService $accountingService,
        protected AuditService $auditService,
        protected TransactionMonitoringService $monitoringService,
        protected CtosReportService $ctosReportService,
        protected TellerAllocationService $tellerAllocationService,
        protected ApprovalWorkflowService $approvalWorkflowService,
        protected UnifiedSanctionScreeningService $screeningService,
    ) {}

    /**
     * Validate currency code exists in system.
     *
     * @param  string  $currencyCode  Currency code to validate
     *
     * @throws \InvalidArgumentException If currency code is invalid
     */
    protected function validateCurrencyCode(string $currencyCode): void
    {
        $currency = Currency::where('code', $currencyCode)
            ->where('is_active', true)
            ->first();

        if (! $currency) {
            throw new \InvalidArgumentException("Invalid or inactive currency code: {$currencyCode}");
        }
    }

    /**
     * Create a new transaction with full validation and compliance checks.
     *
     * @param  array  $data  Validated transaction data
     * @param  int|null  $userId  User creating the transaction (null for API context)
     * @param  string|null  $ipAddress  IP address for audit logging
     *
     * @throws \Exception If transaction creation fails
     */
    public function createTransaction(array $data, ?int $userId = null, ?string $ipAddress = null): Transaction
    {
        $this->validateCurrencyCode($data['currency_code']);

        $userId = $userId ?? auth()->id();
        $ipAddress = $ipAddress ?? request()->ip();

        // Validate IP address format
        if ($ipAddress && ! filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException('Invalid IP address format.');
        }

        // Verify till is open for this currency
        $tillBalance = TillBalance::where('till_id', $data['till_id'])
            ->where('currency_code', $data['currency_code'])
            ->whereDate('date', today())
            ->whereNull('closed_at')
            ->first();

        if (! $tillBalance) {
            throw new \InvalidArgumentException('Till is not open for this currency. Please open the till first.');
        }

        // Get customer and calculate amounts
        $customer = Customer::findOrFail($data['customer_id']);
        $amountForeign = (string) $data['amount_foreign'];
        $rate = (string) $data['rate'];
        $amountLocal = $this->mathService->multiply($amountForeign, $rate);

        // Validate against teller allocation (only for tellers, not manager/admin overrides)
        // Only validate for Buy transactions (teller sells foreign currency and needs allocation)
        // For Sell transactions, no allocation check is needed upfront
        $user = User::findOrFail($userId);
        $allocationForUpdate = null;
        if ($user->role === UserRole::Teller) {
            $isBuy = ($data['type'] === TransactionType::Buy->value);
            if ($isBuy) {
                $validationResult = $this->tellerAllocationService->validateTransaction(
                    $user,
                    $data['currency_code'],
                    $amountLocal,
                    $isBuy
                );

                if (! $validationResult['valid']) {
                    throw new \InvalidArgumentException('Allocation validation failed: '.$validationResult['reason']);
                }

                $allocationForUpdate = $validationResult['allocation'];
            } else {
                // For Sell transactions, get allocation for update after transaction completes
                $allocationForUpdate = $this->tellerAllocationService->getActiveAllocation(
                    $user,
                    $data['currency_code']
                );
            }
        }

        // Determine CDD level
        $cddLevel = $this->complianceService->determineCDDLevel($amountLocal, $customer);
        $holdCheck = $this->complianceService->requiresHold($amountLocal, $customer);

        // Sanction screening via UnifiedSanctionScreeningService
        $complianceFlags = [];
        $screeningResult = $this->screeningService->screenCustomer($customer);
        if ($screeningResult->isFlagged()) {
            $holdCheck['requires_compliance_review'] = true;
            $complianceFlags[] = 'sanction_match';
        }

        // Log CDD decision
        $cddTriggers = [];
        if ($customer->pep_status) {
            $cddTriggers[] = 'PEP customer';
        }
        if ($this->mathService->compare($amountLocal, ComplianceService::LARGE_TRANSACTION_THRESHOLD) >= 0) {
            $cddTriggers[] = 'Large amount >= RM '.number_format((float) ComplianceService::LARGE_TRANSACTION_THRESHOLD);
        } elseif ($this->mathService->compare($amountLocal, ComplianceService::STANDARD_CDD_THRESHOLD) >= 0) {
            $cddTriggers[] = 'Standard amount >= RM '.number_format((float) ComplianceService::STANDARD_CDD_THRESHOLD);
        }
        if ($customer->risk_rating === 'High') {
            $cddTriggers[] = 'High risk customer';
        }

        $this->auditService->logWithSeverity(
            'cdd_decision',
            [
                'user_id' => $userId,
                'entity_type' => 'Transaction',
                'new_values' => [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->full_name,
                    'cdd_level' => $cddLevel->value,
                    'triggers' => $cddTriggers,
                    'amount_local' => $amountLocal,
                ],
            ],
            'INFO'
        );

        // Determine initial status
        $status = TransactionStatus::Completed;
        $holdReason = null;
        $approvedBy = null;

        if ($holdCheck['requires_hold'] || $this->mathService->compare($amountLocal, ApprovalWorkflowService::AUTO_APPROVE_THRESHOLD) >= 0) {
            // All transactions requiring approval (compliance hold OR amount >= RM 3,000)
            // now go to PendingApproval with manager approval required
            $status = TransactionStatus::PendingApproval;
            if ($holdCheck['requires_hold']) {
                $holdReason = implode(', ', $holdCheck['reasons']);
            }
        }

        return DB::transaction(function () use ($data, $userId, $tillBalance, $amountForeign, $rate, $amountLocal, $cddLevel, $status, $holdReason, $approvedBy, &$allocationForUpdate) {
            // For Sell transactions, acquire position lock FIRST to prevent race conditions
            // where two concurrent transactions could both pass the duplicate check
            // before either acquires the lock
            $position = null;
            if ($data['type'] === TransactionType::Sell->value) {
                $position = $this->positionService->getPositionWithLock($data['currency_code'], $data['till_id']);

                // Verify sufficient stock for Sell transactions IMMEDIATELY after acquiring lock
                // This prevents race conditions where another transaction could modify the position
                // Use getAvailableBalance which accounts for pending reservations
                $availableBalance = $this->positionService->getAvailableBalance(
                    $data['currency_code'],
                    $data['till_id']
                );
                if ($this->mathService->compare($availableBalance, $amountForeign) < 0) {
                    throw new InsufficientStockException(
                        $data['currency_code'],
                        $amountForeign,
                        $availableBalance
                    );
                }
            } elseif ($data['type'] === TransactionType::Buy->value) {
                // For Buy transactions, acquire position lock to prevent race conditions
                // where concurrent transactions could cause inconsistent position updates.
                // Unlike Sell, Buy does not require stock validation (we are acquiring currency).
                $position = $this->positionService->getPositionWithLock($data['currency_code'], $data['till_id']);
            }

            // Check for duplicate transaction via idempotency key (inside transaction to prevent race)
            // Check this FIRST, before recent duplicate window, as idempotency is the strongest guarantee
            if (! empty($data['idempotency_key'])) {
                $existingByKey = Transaction::where('idempotency_key', $data['idempotency_key'])->first();
                if ($existingByKey) {
                    return $existingByKey;
                }
            }

            // Check for recent similar transaction (potential double-submit)
            // Moved inside DB transaction to ensure check and insert are atomic
            $recentWindow = now()->subSeconds(30);
            $recentAmount = Transaction::where('user_id', $userId)
                ->where('created_at', '>=', $recentWindow)
                ->where('amount_foreign', $data['amount_foreign'])
                ->where('currency_code', $data['currency_code'])
                ->where('type', $data['type'])
                ->first();

            if ($recentAmount) {
                $this->auditService->logWithSeverity(
                    'potential_duplicate_detected',
                    [
                        'user_id' => $userId,
                        'entity_type' => 'Transaction',
                        'entity_id' => $recentAmount->id,
                        'description' => "Similar transaction {$recentAmount->id} found within 30 seconds",
                    ],
                    'WARNING'
                );

                throw new \InvalidArgumentException('Potential duplicate transaction detected. Please wait 30 seconds before submitting again or check your recent transactions.');
            }

            $transaction = Transaction::create([
                'customer_id' => $data['customer_id'],
                'user_id' => $userId,
                'branch_id' => $tillBalance->branch_id,
                'till_id' => $data['till_id'],
                'type' => $data['type'],
                'currency_code' => $data['currency_code'],
                'amount_foreign' => $amountForeign,
                'amount_local' => $amountLocal,
                'rate' => $rate,
                'purpose' => $data['purpose'],
                'source_of_funds' => $data['source_of_funds'],
                'status' => $status,
                'hold_reason' => $holdReason,
                'approved_by' => $approvedBy,
                'cdd_level' => $cddLevel,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'version' => 0,
            ]);

            // If transaction requires approval (>= RM 3,000 and no compliance hold),
            // create the approval task atomically within the same transaction.
            // This ensures transaction and task are always linked; if task creation fails,
            // the entire transaction rolls back.
            if ($status === TransactionStatus::PendingApproval) {
                // Reserve the stock immediately so it cannot be oversold
                $this->positionService->reserveStock($transaction);

                try {
                    $this->approvalWorkflowService->createApprovalTask($transaction);
                } catch (\Exception $e) {
                    Log::error('Approval task creation failed', [
                        'transaction_id' => $transaction->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Re-throw to rollback the transaction
                    throw $e;
                }
            }

            // If completed, update positions, till balance, and create accounting entries
            if ($status === TransactionStatus::Completed) {
                $this->positionService->updatePosition(
                    $data['currency_code'],
                    $amountForeign,
                    $rate,
                    $data['type'],
                    $data['till_id']
                );
                $this->updateTillBalance($tillBalance, $data['type'], $amountLocal, $amountForeign);

                // Update teller allocation if this was a teller transaction
                if ($allocationForUpdate) {
                    $isBuy = ($data['type'] === TransactionType::Buy->value);
                    if ($isBuy) {
                        $allocationForUpdate->deduct($amountForeign);
                    } else {
                        $allocationForUpdate->add($amountForeign);
                    }
                    $allocationForUpdate->addDailyUsed($amountLocal);
                }

                $this->createAccountingEntries($transaction);
            }

            $this->auditService->logWithSeverity(
                'transaction_created',
                [
                    'user_id' => $userId,
                    'entity_type' => 'Transaction',
                    'entity_id' => $transaction->id,
                    'new_values' => [
                        'type' => $transaction->type,
                        'amount_local' => $transaction->amount_local,
                        'amount_foreign' => $transaction->amount_foreign,
                        'currency' => $transaction->currency_code,
                        'status' => $transaction->status,
                        'cdd_level' => $cddLevel,
                    ],
                ],
                'INFO'
            );

            // Generate CTOS report if transaction qualifies (>= RM 10,000 cash transaction)
            if ($this->ctosReportService->qualifiesForCtos($transaction)) {
                try {
                    $this->ctosReportService->createFromTransaction($transaction, $userId);
                } catch (\Exception $e) {
                    Log::error('CTOS report creation failed', [
                        'transaction_id' => $transaction->id,
                        'error' => $e->getMessage(),
                    ]);
                    $this->auditService->logWithSeverity(
                        'ctos_report_creation_failed',
                        [
                            'entity_type' => 'Transaction',
                            'entity_id' => $transaction->id,
                            'new_values' => [
                                'error' => $e->getMessage(),
                                'requires_manual_submission' => true,
                            ],
                        ],
                        'WARNING'
                    );
                }
            }

            // Dispatch event for async processing
            Event::dispatch(new TransactionCreated($transaction));

            return $transaction;
        });
    }

    /**
     * Verify till is still open for operations.
     *
     * @param  TillBalance  $tillBalance  The till balance to verify
     *
     * @throws \InvalidArgumentException If till is closed
     */
    protected function verifyTillIsOpen(TillBalance $tillBalance): void
    {
        if ($tillBalance->closed_at !== null) {
            throw new \InvalidArgumentException('Till is closed. Cannot perform operations on closed till.');
        }
    }

    /**
     * Update till balance after transaction.
     * Updates both foreign currency and MYR (local currency) balances.
     * Uses lockForUpdate to prevent race conditions on concurrent transactions.
     */
    protected function updateTillBalance(TillBalance $tillBalance, string $type, string $amountLocal, string $amountForeign): void
    {
        $this->verifyTillIsOpen($tillBalance);

        // Lock the foreign currency balance
        $lockedForeign = TillBalance::where('id', $tillBalance->id)
            ->lockForUpdate()
            ->first();

        // Lock the MYR balance (always present for active till)
        $myrBalance = TillBalance::where('till_id', $lockedForeign->till_id)
            ->where('currency_code', 'MYR')
            ->whereDate('date', today())
            ->whereNull('closed_at')
            ->lockForUpdate()
            ->first();

        if (! $myrBalance) {
            throw new TillBalanceMissingException('MYR', $lockedForeign->till_id);
        }

        // Update foreign currency balance
        $foreignTotal = $lockedForeign->foreign_total ?? '0';
        $newForeignTotal = $type === TransactionType::Buy->value
            ? $this->mathService->add($foreignTotal, $amountForeign)
            : $this->mathService->subtract($foreignTotal, $amountForeign);

        $lockedForeign->update(['foreign_total' => $newForeignTotal]);

        // Update MYR balance - always add (cash in on Sell, cash out on Buy is recorded separately)
        $myrTotal = $myrBalance->transaction_total ?? '0';
        $newMyrTotal = $this->mathService->add($myrTotal, $amountLocal);

        $myrBalance->update(['transaction_total' => $newMyrTotal]);
    }

    /**
     * Create accounting journal entries for transaction.
     * For Enhanced CDD transactions, defers creation until approval.
     */
    protected function createAccountingEntries(Transaction $transaction): void
    {
        // Check if Enhanced CDD and not yet approved (status is PendingApproval)
        if ($transaction->cdd_level === CddLevel::Enhanced
            && $transaction->status !== TransactionStatus::Completed) {
            Log::info('Deferring journal entry creation for Enhanced CDD transaction', [
                'transaction_id' => $transaction->id,
                'status' => $transaction->status->value,
                'cdd_level' => $transaction->cdd_level->value,
            ]);

            $this->auditService->logTransaction('journal_entries_deferred', $transaction->id, [
                'cdd_level' => $transaction->cdd_level->value,
                'status' => $transaction->status->value,
                'reason' => 'Enhanced CDD requires approval before bookkeeping',
            ]);

            return;
        }

        // Create entries immediately for Simplified/Standard CDD or approved Enhanced CDD
        $this->createImmediateAccountingEntries($transaction);
    }

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
    protected function createImmediateAccountingEntries(Transaction $transaction): void
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

    /**
     * Approve a pending transaction and complete its side effects.
     *
     * This method handles the full approval workflow for transactions that were
     * created with 'Pending' status (typically >= RM 50,000).
     *
     * @param  Transaction  $transaction  The pending transaction to approve
     * @param  int  $approverId  The user ID of the manager/admin approving
     * @param  string|null  $ipAddress  IP address for audit logging
     * @return array{success: bool, message: string, transaction?: Transaction}
     *
     * @throws \InvalidArgumentException If transaction is not pending
     * @throws \RuntimeException If transaction was already processed
     */
    public function approveTransaction(Transaction $transaction, int $approverId, ?string $ipAddress = null): array
    {
        $ipAddress = $ipAddress ?? request()->ip();

        // Validate IP address format
        if ($ipAddress && ! filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException('Invalid IP address format.');
        }

        // Validate transaction is in pending approval status
        if ($transaction->status !== TransactionStatus::PendingApproval) {
            throw new \InvalidArgumentException(
                'Transaction is not pending approval. Current status: '.$transaction->status->label()
            );
        }

        // Re-run compliance monitoring before approval
        // If high-priority AML flags are generated, approval is blocked
        $amlResult = $this->monitoringService->monitorTransaction($transaction);
        $highPriorityFlags = array_filter(
            $amlResult['flags'],
            fn ($flag) => $flag->flag_type->isHighPriority()
        );

        if (! empty($highPriorityFlags)) {
            $flagTypes = implode(', ', array_map(
                fn ($f) => $f->flag_type->label(),
                $highPriorityFlags
            ));

            $this->auditService->logWithSeverity(
                'transaction_approval_blocked',
                [
                    'user_id' => $approverId,
                    'entity_type' => 'Transaction',
                    'entity_id' => $transaction->id,
                    'new_values' => [
                        'reason' => 'High-priority AML flags',
                        'flags' => $flagTypes,
                    ],
                ],
                'WARNING'
            );

            return [
                'success' => false,
                'message' => "Approval blocked: High-priority AML flags generated ({$flagTypes}). Transaction remains pending for compliance review.",
            ];
        }

        try {
            return DB::transaction(function () use ($transaction, $approverId, $amlResult) {
                // Optimistic locking with pessimistic lock to prevent race conditions
                $lockedTransaction = Transaction::where('id', $transaction->id)
                    ->where('status', TransactionStatus::PendingApproval)
                    ->where('version', $transaction->version)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedTransaction) {
                    throw new \RuntimeException(
                        'Transaction was already processed or modified by another user.'
                    );
                }

                // Build proper transition history: record both Approval and Completion as separate steps
                $history = $lockedTransaction->transition_history ?? [];
                $nowIso = now()->toIso8601String();

                // Determine "from" state based on original status
                $fromState = $lockedTransaction->status->value;

                // Step 1: Pending/PendingApproval -> Approved
                $history[] = [
                    'from' => $fromState,
                    'to' => TransactionStatus::Approved->value,
                    'reason' => 'Transaction approved by manager',
                    'user_id' => $approverId,
                    'timestamp' => $nowIso,
                ];

                // Step 2: Approved -> Completed
                $history[] = [
                    'from' => TransactionStatus::Approved->value,
                    'to' => TransactionStatus::Completed->value,
                    'reason' => 'Transaction completed after approval',
                    'user_id' => $approverId,
                    'timestamp' => $nowIso,
                ];

                // Perform the update with proper history and version increment
                $lockedTransaction->update([
                    'status' => TransactionStatus::Completed,
                    'approved_by' => $approverId,
                    'approved_at' => $nowIso,
                    'transition_history' => $history,
                    'version' => $lockedTransaction->version + 1,
                ]);

                // Refresh the model to get updated version
                $lockedTransaction->refresh();

                // Get the till balance for today
                $tillBalance = TillBalance::where('till_id', $lockedTransaction->till_id ?? 'MAIN')
                    ->where('currency_code', $lockedTransaction->currency_code)
                    ->whereDate('date', today())
                    ->whereNull('closed_at')
                    ->first();

                if (! $tillBalance) {
                    throw new \RuntimeException(
                        'Till balance not found for today. Cannot complete transaction.'
                    );
                }

                // Consume the stock reservation (all PendingApproval transactions have reservations)
                $reservation = $this->positionService->consumeStockReservation($lockedTransaction->id);

                if (! $reservation) {
                    throw new StockReservationExpiredException($lockedTransaction->id);
                }

                // Verify stock is still available (reservation protects this, but double-check)
                $available = $this->positionService->getAvailableBalance(
                    $lockedTransaction->currency_code,
                    (string) $lockedTransaction->till_id
                );

                if ($this->mathService->compare($available, (string) $lockedTransaction->amount_foreign) < 0) {
                    $this->positionService->releaseStockReservation($lockedTransaction->id);
                    throw new InsufficientStockException(
                        $lockedTransaction->currency_code,
                        (string) $lockedTransaction->amount_foreign,
                        $available
                    );
                }

                // Execute position and till balance updates
                $this->positionService->updatePosition(
                    $lockedTransaction->currency_code,
                    (string) $lockedTransaction->amount_foreign,
                    (string) $lockedTransaction->rate,
                    $lockedTransaction->type->value,
                    $lockedTransaction->till_id ?? 'MAIN'
                );
                $this->updateTillBalance(
                    $tillBalance,
                    $lockedTransaction->type->value,
                    (string) $lockedTransaction->amount_local,
                    (string) $lockedTransaction->amount_foreign
                );

                // Create double-entry accounting journal entries
                // For Enhanced CDD transactions, use deferred entry creation (approval triggers it)
                if ($lockedTransaction->cdd_level === CddLevel::Enhanced) {
                    $this->createDeferredAccountingEntries($lockedTransaction->id);
                } else {
                    $this->createAccountingEntries($lockedTransaction);
                }

                // Audit logging for the approval action
                $this->auditService->logTransaction('transaction_approved', $lockedTransaction->id, [
                    'old' => [
                        'status' => TransactionStatus::PendingApproval->value,
                        'approved_by' => null,
                    ],
                    'new' => [
                        'status' => TransactionStatus::Completed->value,
                        'approved_by' => $approverId,
                        'approved_at' => $lockedTransaction->approved_at->toIso8601String(),
                        'aml_flags_checked' => $amlResult['flags_created'] ?? 0,
                    ],
                ]);

                // Sync ApprovalTask after completion (all PendingApproval transactions have tasks)
                try {
                    $this->syncApprovalTaskOnCompletion($lockedTransaction, $approverId);
                } catch (\Exception $e) {
                    // Log but don't fail the transaction - sync failures are non-blocking
                    Log::error('ApprovalTask sync error (non-blocking)', [
                        'transaction_id' => $lockedTransaction->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Dispatch event for async compliance processing
                Event::dispatch(new TransactionApproved($lockedTransaction));

                return [
                    'success' => true,
                    'message' => 'Transaction approved and completed successfully.',
                    'transaction' => $lockedTransaction->fresh(),
                ];
            });
        } catch (InsufficientStockException $e) {
            return [
                'success' => false,
                'message' => 'Insufficient stock: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Sync the ApprovalTask when a PendingApproval transaction is completed.
     *
     * This ensures the approval workflow audit trail is complete by marking
     * the associated ApprovalTask as approved.
     */
    protected function syncApprovalTaskOnCompletion(Transaction $transaction, int $approverId): void
    {
        $maxRetries = 3;
        $attempt = 0;
        $lastError = null;

        while ($attempt < $maxRetries) {
            try {
                // Find the pending ApprovalTask for this transaction
                $task = ApprovalTask::where('transaction_id', $transaction->id)
                    ->where('status', ApprovalTask::STATUS_PENDING)
                    ->first();

                if (! $task) {
                    Log::warning('ApprovalTask not found for PendingApproval transaction', [
                        'transaction_id' => $transaction->id,
                        'attempt' => $attempt + 1,
                    ]);

                    return;
                }

                // Get the approver user
                $approver = User::findOrFail($approverId);

                // Approve the task via ApprovalWorkflowService
                $this->approvalWorkflowService->approve($task, $approver, 'Transaction completed via approveTransaction');

                Log::info('ApprovalTask synced on transaction completion', [
                    'transaction_id' => $transaction->id,
                    'approval_task_id' => $task->id,
                    'approver_id' => $approverId,
                    'attempt' => $attempt + 1,
                ]);

                return; // Success, exit retry loop
            } catch (\Exception $e) {
                $attempt++;
                $lastError = $e->getMessage();

                if ($attempt < $maxRetries) {
                    // Exponential backoff: 200ms, 400ms
                    $delay = pow(2, $attempt) * 100;
                    usleep($delay * 1000);
                }
            }
        }

        // All retries failed - mark transaction and log error
        Log::error('ApprovalTask sync failed after retries', [
            'transaction_id' => $transaction->id,
            'approver_id' => $approverId,
            'attempts' => $maxRetries,
            'error' => $lastError,
        ]);

        // Mark transaction as failed sync
        $transaction->update([
            'approval_sync_failed' => true,
            'approval_sync_failed_at' => now(),
            'approval_sync_error' => $lastError,
        ]);

        // Create audit log entry
        $this->auditService->logWithSeverity(
            'approval_task_sync_failed',
            [
                'user_id' => $approverId,
                'entity_type' => 'Transaction',
                'entity_id' => $transaction->id,
                'new_values' => [
                    'error' => $lastError,
                    'attempts' => $maxRetries,
                    'requires_manual_review' => true,
                ],
            ],
            'ERROR'
        );
    }
}
