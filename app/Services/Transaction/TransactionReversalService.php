<?php

namespace App\Services\Transaction;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\AuditService;
use App\Services\Branch\TellerAllocationService;
use App\Services\Compliance\ComplianceService;
use App\Services\System\MathService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionReversalService
{
    public function __construct(
        protected MathService $mathService,
        protected AccountingService $accountingService,
        protected AuditService $auditService,
        protected ComplianceService $complianceService,
        protected CurrencyPositionService $positionService,
        protected TellerAllocationService $tellerAllocationService,
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
                throw new \RuntimeException('Failed to transition transaction to Reversed');
            }

            // 2. Compensating side effects only run after the transition succeeds.
            $refundTransaction = $this->createRefundTransaction($lockedTransaction, $requester->id);
            $this->reversePositions($lockedTransaction);
            $this->reverseTillBalance($lockedTransaction);
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
        if ($user->role->isManager()) {
            return true;
        }

        return $transaction->user_id === $user->id;
    }

    public function isWithinCancellationWindow(Transaction $transaction): bool
    {
        $windowHours = config('cems.transaction_cancellation_window_hours', 24);

        return $transaction->created_at->diffInHours(now()) <= $windowHours;
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

        $amountLocal = $this->mathService->multiply(
            (string) $original->amount_foreign,
            (string) $original->rate
        );

        $customer = Customer::findOrFail($original->customer_id);
        $holdCheck = $this->complianceService->requiresHold($amountLocal, $customer);

        $status = TransactionStatus::Completed;
        $holdReason = null;
        if ($holdCheck->requiresHold) {
            $status = TransactionStatus::PendingApproval;
            $holdReason = implode(', ', $holdCheck->reasons);
        }

        $refund = Transaction::create([
            'customer_id' => $original->customer_id,
            'user_id' => $original->user_id,
            'branch_id' => $original->branch_id,
            'till_id' => $original->till_id,
            'type' => $oppositeType,
            'currency_code' => $original->currency_code,
            'amount_foreign' => $original->amount_foreign,
            'amount_local' => $amountLocal,
            'rate' => $original->rate,
            'purpose' => 'Reversal: '.($original->purpose ?? 'Transaction reversal'),
            'source_of_funds' => $original->source_of_funds,
            'cdd_level' => $original->cdd_level,
            'original_transaction_id' => $original->id,
        ]);

        $refund->status = $status;
        $refund->hold_reason = $holdReason;
        $refund->is_refund = true;
        $refund->approved_by = $status->isCompleted() ? $approvedBy : null;
        $refund->approved_at = $status->isCompleted() ? now() : null;
        $refund->save();

        $this->auditService->logWithSeverity(
            'refund_compliance_check',
            [
                'user_id' => $approvedBy,
                'entity_type' => 'Transaction',
                'entity_id' => $refund->id,
                'new_values' => [
                    'original_transaction_id' => $original->id,
                    'amount_local' => $amountLocal,
                    'status' => $status->value,
                    'hold_reason' => $holdReason,
                    'compliance_reasons' => $holdCheck->reasons,
                ],
            ],
            'INFO'
        );

        return $refund;
    }

    public function reversePositions(Transaction $transaction): void
    {
        $position = CurrencyPosition::where('currency_code', $transaction->currency_code)
            ->where('branch_id', $transaction->branch_id)
            ->lockForUpdate()
            ->first();

        if (! $position) {
            Log::warning('No position found for reversal', [
                'transaction_id' => $transaction->id,
                'currency_code' => $transaction->currency_code,
                'branch_id' => $transaction->branch_id,
            ]);

            return;
        }

        $reversalType = $transaction->type === TransactionType::Buy
            ? TransactionType::Sell
            : TransactionType::Buy;

        $this->positionService->updatePosition(
            $transaction->currency_code,
            $transaction->amount_foreign,
            $transaction->rate,
            $reversalType->value,
            $transaction->branch_id
        );

        Log::info('Positions reversed for transaction', [
            'transaction_id' => $transaction->id,
            'currency_code' => $transaction->currency_code,
            'amount_foreign' => $transaction->amount_foreign,
            'reversal_type' => $reversalType->value,
        ]);
    }

    protected function reverseTillBalance(Transaction $transaction): void
    {
        $tillBalance = TillBalance::where('till_id', $transaction->till_id)
            ->where('currency_code', $transaction->currency_code)
            ->whereDate('date', today())
            ->whereNull('closed_at')
            ->lockForUpdate()
            ->first();

        if (! $tillBalance) {
            Log::warning('No open till balance found for reversal', [
                'transaction_id' => $transaction->id,
                'till_id' => $transaction->till_id,
                'currency_code' => $transaction->currency_code,
            ]);

            return;
        }

        $isBuy = $transaction->type === TransactionType::Buy;

        $foreignTotal = $tillBalance->foreign_total ?? '0';
        if ($isBuy) {
            $newForeignTotal = $this->mathService->subtract($foreignTotal, $transaction->amount_foreign);
            $buyTotal = $tillBalance->buy_total_foreign ?? '0';
            $newBuyTotal = $this->mathService->subtract($buyTotal, $transaction->amount_foreign);
            $tillBalance->update([
                'foreign_total' => $newForeignTotal,
                'buy_total_foreign' => $newBuyTotal,
            ]);
        } else {
            $newForeignTotal = $this->mathService->add($foreignTotal, $transaction->amount_foreign);
            $sellTotal = $tillBalance->sell_total_foreign ?? '0';
            $newSellTotal = $this->mathService->add($sellTotal, $transaction->amount_foreign);
            $tillBalance->update([
                'foreign_total' => $newForeignTotal,
                'sell_total_foreign' => $newSellTotal,
            ]);
        }

        $myrTillBalance = TillBalance::where('till_id', $transaction->till_id)
            ->where('currency_code', 'MYR')
            ->whereDate('date', today())
            ->whereNull('closed_at')
            ->lockForUpdate()
            ->first();

        if ($myrTillBalance) {
            $myrTotal = $myrTillBalance->transaction_total ?? '0';
            $newMyrTotal = $transaction->type->isBuy()
                ? $this->mathService->add($myrTotal, $transaction->amount_local)
                : $this->mathService->subtract($myrTotal, $transaction->amount_local);
            $myrTillBalance->update(['transaction_total' => $newMyrTotal]);
        }

        Log::info('Till balance reversed for transaction', [
            'transaction_id' => $transaction->id,
            'currency_code' => $transaction->currency_code,
            'amount_foreign' => $transaction->amount_foreign,
            'amount_local' => $transaction->amount_local,
        ]);
    }

    public function createReversingJournalEntries(Transaction $transaction, ?int $reversedBy = null): void
    {
        $reversedBy = $reversedBy ?? auth()->id();

        $originalEntries = JournalEntry::where('reference_type', 'Transaction')
            ->where('reference_id', $transaction->id)
            ->where('status', 'Posted')
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
            } catch (\InvalidArgumentException $e) {
                Log::warning('Failed to reverse journal entry', [
                    'original_entry_id' => $originalEntry->id,
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function reverseTellerAllocation(Transaction $transaction): void
    {
        $user = User::find($transaction->user_id);
        if ($user && $user->isTeller()) {
            $allocation = $this->tellerAllocationService->getActiveAllocation(
                $user,
                $transaction->currency_code
            );
            if ($allocation) {
                $allocation = TellerAllocation::where('id', $allocation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($transaction->type->isBuy()) {
                    $allocation->deduct((string) $transaction->amount_foreign);
                    $allocation->subtractDailyUsed((string) $transaction->amount_local);
                } else {
                    $allocation->add((string) $transaction->amount_foreign);
                    $allocation->subtractDailyUsed((string) $transaction->amount_local);
                }
            }
        }
    }
}
