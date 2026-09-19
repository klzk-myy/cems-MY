<?php

namespace App\Services\Transaction;

use App\Enums\JournalEntryStatus;
use App\Enums\Permission;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Exceptions\Domain\TransactionAlreadyProcessedException;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\CurrencyPositionLockService;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Audit\AuditTrailHelper;
use App\Services\Branch\TellerAllocationService;
use App\Services\Branch\TillBalanceManager;
use App\Services\Compliance\ComplianceService;
use App\Services\System\MathService;
use App\Support\ActorContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TransactionReversalService
{
    public function __construct(
        protected MathService $mathService,
        protected AccountingService $accountingService,
        protected AuditTrailHelper $auditTrailHelper,
        protected ComplianceService $complianceService,
        protected CurrencyPositionService $positionService,
        protected TellerAllocationService $tellerAllocationService,
        protected CurrencyPositionLockService $positionLockService,
        protected TillBalanceManager $tillBalanceManager,
        protected ExchangeCalculator $exchangeCalculator,
    ) {}

    public function reverse(Transaction $transaction, User $requester, string $reason): bool
    {
        $result = DB::transaction(function () use ($transaction, $requester, $reason) {
            // 1. Enforce the state transition FIRST. If it fails, nothing else happens.
            $lockedTransaction = Transaction::where('id', $transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $stateMachine = new TransactionStateMachine($lockedTransaction);
            if (! $stateMachine->transitionTo(TransactionStatus::Reversed, [
                'reason' => $reason,
                'user_id' => $requester->id,
            ])) {
                throw new TransactionAlreadyProcessedException($transaction->id);
            }

            // 2. Compensating side effects only run after the transition succeeds.
            $refundTransaction = $this->createRefundTransaction($lockedTransaction, $requester->id);
            $this->reversePositions($lockedTransaction);

            // The till may have no open balance today (e.g. it was closed
            // before an older transaction is reversed). Skip the till leg -
            // its books were already sealed - but still complete the FX,
            // journal and allocation reversal legs.
            try {
                $this->reverseTillBalance($lockedTransaction);
            } catch (TillBalanceMissingException $e) {
                Log::warning('Skipping till balance reversal - no open till balance', [
                    'transaction_id' => $lockedTransaction->id,
                    'till_id' => $lockedTransaction->till_id,
                    'currency_code' => $lockedTransaction->currency_code,
                ]);
            }

            $this->createReversingJournalEntries($lockedTransaction, $requester->id);
            $this->reverseTellerAllocation($lockedTransaction);

            Log::info('Transaction reversal processed', [
                'transaction_id' => $lockedTransaction->id,
                'refund_transaction_id' => $refundTransaction->id,
                'reversed_by' => $requester->id,
                'reason' => $reason,
            ]);

            return true;
        });

        $transaction->refresh();

        return $result;
    }

    public function canReverse(Transaction $transaction): bool
    {
        if (! $transaction->status->isCompleted()) {
            return false;
        }

        if ($transaction->status->isReversed()) {
            return false;
        }

        if ($transaction->is_refund) {
            return false;
        }

        return $this->isWithinCancellationWindow($transaction);
    }

    public function canUserReverse(User $user, Transaction $transaction): bool
    {
        // Reversals of completed transactions are compliance-only by default.
        return $user->role->canPerform(Permission::ReverseTransactions);
    }

    public function isWithinCancellationWindow(Transaction $transaction): bool
    {
        $windowMinutes = config('cems.transaction_cancellation_window_hours', 24) * 60;

        return $transaction->created_at->diffInMinutes(now()) <= $windowMinutes;
    }

    public function getCancellationWindowHours(): int
    {
        return (int) config('cems.transaction_cancellation_window_hours', 24);
    }

    public function createRefundTransaction(Transaction $original, int $approvedBy): Transaction
    {
        $oppositeType = $original->type === TransactionType::Buy
            ? TransactionType::Sell
            : TransactionType::Buy;

        $amountMyr = $this->exchangeCalculator->calculate(
            $oppositeType,
            $original->currency_code,
            (string) $original->quantity,
            (string) $original->rate
        )['amount_myr'];

        $customer = Customer::findOrFail($original->customer_id);
        $holdCheck = $this->complianceService->requiresHold($amountMyr, $customer);

        // Refund transactions are inherently high-risk (reversals) and should always
        // require manager approval regardless of amount. This prevents bypassing
        // compliance controls through reversal/refund workflows.
        $status = TransactionStatus::PendingApproval;
        $holdReason = 'Refund transaction requires manager approval';

        if ($holdCheck->requiresHold) {
            $holdReason = 'Refund: '.implode(', ', $holdCheck->reasons);
        }

        $refund = new Transaction([
            'customer_id' => $original->customer_id,
            'user_id' => $original->user_id,
            'branch_id' => $original->branch_id,
            'till_id' => $original->till_id,
            'type' => $oppositeType,
            'currency_code' => $original->currency_code,
            'quantity' => $original->quantity,
            'amount_myr' => $amountMyr,
            'rate' => $original->rate,
            'purpose' => 'Reversal: '.($original->purpose ?? 'Transaction reversal'),
            'source_of_funds' => $original->source_of_funds,
        ]);

        $refund->cdd_level = $original->cdd_level;
        $refund->original_transaction_id = $original->id;
        $refund->status = $status;
        $refund->hold_reason = $holdReason;
        $refund->is_refund = true;
        $refund->approved_by = null;
        $refund->approved_at = null;
        $refund->save();

        $this->auditTrailHelper->recordTransactionSealed(
            $refund->id,
            'refund_compliance_check',
            [
                'new' => [
                    'original_transaction_id' => $original->id,
                    'amount_myr' => $amountMyr,
                    'status' => $status->value,
                    'hold_reason' => $holdReason,
                    'compliance_reasons' => $holdCheck->reasons,
                    'note' => 'Refund transactions always require approval per compliance policy',
                ],
            ],
            User::find($approvedBy),
            'CRITICAL'
        );

        return $refund;
    }

    /**
     * Reverse the currency-position impact of a transaction.
     *
     * Delegates to CurrencyPositionService::reversePositions(), the guard-free
     * compensating path: routing through updatePosition() with the opposite
     * type could hard-fail a Buy reversal (insufficient-balance guard) after
     * the acquired currency was re-sold, and corrupted the average cost when
     * reversing a Sell. A missing position throws so the cancellation
     * cannot commit without restoring position state.
     */
    public function reversePositions(Transaction $transaction): void
    {
        $position = $this->positionLockService->findForUpdate(
            $transaction->branch_id !== null ? (string) $transaction->branch_id : null,
            $transaction->currency_code
        );

        if (! $position) {
            throw new RuntimeException(sprintf(
                'No currency position found to reverse transaction %d (currency %s, branch %s)',
                $transaction->id,
                $transaction->currency_code,
                $transaction->branch_id ?? 'null'
            ));
        }

        $this->positionService->reversePositions($transaction);

        Log::info('Positions reversed for transaction', [
            'transaction_id' => $transaction->id,
            'currency_code' => $transaction->currency_code,
            'quantity' => $transaction->quantity,
            'type' => $transaction->type->value,
        ]);
    }

    public function reverseTillBalance(Transaction $transaction): void
    {
        $counter = Counter::findByCodeOrId($transaction->till_id);

        if (! $counter) {
            Log::warning('No counter found for reversal', [
                'transaction_id' => $transaction->id,
                'till_id' => $transaction->till_id,
            ]);

            // Same compensating semantics as a missing till balance: the till
            // leg cannot be reversed, so signal the caller to skip it rather
            // than aborting the whole reversal after positions were restored.
            throw new TillBalanceMissingException($transaction->currency_code, (string) $transaction->till_id);
        }

        $tillBalance = $this->tillBalanceManager->currentBalance($counter, $transaction->currency_code, true);

        if (! $tillBalance) {
            Log::warning('No open till balance found for reversal', [
                'transaction_id' => $transaction->id,
                'till_id' => $transaction->till_id,
                'currency_code' => $transaction->currency_code,
            ]);

            // Same signal TillBalanceManager::reverseTransaction() raises for
            // a missing open balance, so callers can treat both paths alike.
            throw new TillBalanceMissingException($transaction->currency_code, (string) $transaction->till_id);
        }

        $this->tillBalanceManager->reverseTransaction(
            $tillBalance,
            $transaction->type,
            (string) $transaction->amount_myr,
            (string) $transaction->quantity
        );

        Log::info('Till balance reversed for transaction', [
            'transaction_id' => $transaction->id,
            'currency_code' => $transaction->currency_code,
            'quantity' => $transaction->quantity,
            'amount_myr' => $transaction->amount_myr,
        ]);
    }

    public function createReversingJournalEntries(Transaction $transaction, ?int $reversedBy = null): void
    {
        $reversedBy = $reversedBy ?? ActorContext::capture()->userId;

        $originalEntries = JournalEntry::where('reference_type', 'Transaction')
            ->where('reference_id', $transaction->id)
            ->where('status', JournalEntryStatus::Posted->value)
            ->get();

        foreach ($originalEntries as $originalEntry) {
            try {
                $this->accountingService->reverseJournalEntry(
                    $originalEntry,
                    "Reversal of transaction {$transaction->id}",
                    $reversedBy
                );

                Log::info('Reversed journal entry', [
                    'original_entry_id' => $originalEntry->id,
                    'transaction_id' => $transaction->id,
                ]);
            } catch (DomainException $e) {
                Log::error('Failed to reverse journal entry', [
                    'original_entry_id' => $originalEntry->id,
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);

                // Do NOT swallow: a reversal that cannot reverse its journal
                // entries would leave positions/till already reversed while the
                // transaction is marked Reversed, breaking the books. Rethrow so
                // the surrounding DB transaction rolls back the whole reversal.
                throw $e;
            }
        }
    }

    protected function reverseTellerAllocation(Transaction $transaction): void
    {
        $this->tellerAllocationService->reverseTransactionAllocation($transaction);
    }
}
