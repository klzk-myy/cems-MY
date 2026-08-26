<?php

namespace App\Services\Branch;

use App\Enums\TellerAllocationStatus;
use App\Enums\TransactionType;
use App\Exceptions\Domain\AllocationValidationException;
use App\Exceptions\Domain\InsufficientPoolBalanceException;
use App\Exceptions\Domain\InvalidAllocationStateException;
use App\Exceptions\Domain\PendingAllocationNotFoundException;
use App\Exceptions\Domain\PoolAllocationException;
use App\Exceptions\Domain\TellerBranchRequiredException;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\TellerAllocation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Contracts\TellerAllocationServiceInterface;
use App\Services\DTOs\AllocationValidationResult;
use App\Services\System\MathService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TellerAllocationService implements TellerAllocationServiceInterface
{
    public function __construct(
        protected BranchPoolService $branchPoolService,
        protected MathService $mathService,
    ) {}

    public function requestAllocation(User $teller, User $approver, string $currencyCode, string $requestedAmount, ?string $dailyLimitMyr = null, ?Counter $counter = null): TellerAllocation
    {
        $branch = $teller->branch;

        if (! $branch instanceof Branch) {
            throw new TellerBranchRequiredException;
        }

        $pool = $this->branchPoolService->getOrCreateForBranch($branch, $currencyCode);

        if (! $pool->hasAvailable($requestedAmount)) {
            throw new InsufficientPoolBalanceException($currencyCode, (string) $pool->available_balance, $requestedAmount);
        }

        $allocationData = [
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'counter_id' => $counter?->id,
            'currency_code' => $currencyCode,
            'requested_amount' => $requestedAmount,
            'allocated_amount' => $requestedAmount,
            'current_balance' => 0,
            'daily_used_myr' => 0,
            'status' => TellerAllocationStatus::PENDING->value,
            'session_date' => now()->toDateString(),
        ];

        if ($dailyLimitMyr !== null) {
            $allocationData['daily_limit_myr'] = $dailyLimitMyr;
        }

        $allocation = TellerAllocation::create($allocationData);

        return $allocation;
    }

    public function approveAllocation(TellerAllocation $allocation, User $approver, string $approvedAmount, ?string $dailyLimitMyr = null): TellerAllocation
    {
        return DB::transaction(function () use ($allocation, $approver, $approvedAmount, $dailyLimitMyr) {
            $locked = TellerAllocation::where('id', $allocation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isPending()) {
                throw new InvalidAllocationStateException(TellerAllocationStatus::PENDING->value);
            }

            if (! $this->branchPoolService->allocateToTeller($this->allocationBranchOrFail($locked), $locked->currency_code, $approvedAmount)) {
                throw new PoolAllocationException;
            }

            $locked->approve($approver, $approvedAmount, $dailyLimitMyr);

            $allocation->refresh();

            return $allocation;
        });
    }

    public function activateAllocation(TellerAllocation $allocation): TellerAllocation
    {
        if (! $allocation->isApproved()) {
            throw new InvalidAllocationStateException(TellerAllocationStatus::APPROVED->value);
        }

        $allocation->activate();

        return $allocation;
    }

    public function modifyAllocation(TellerAllocation $allocation, User $modifier, string $newAmount, bool $isIncrease): TellerAllocation
    {
        return DB::transaction(function () use ($allocation, $newAmount, $isIncrease) {
            $locked = TellerAllocation::where('id', $allocation->id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                throw new PendingAllocationNotFoundException($allocation->currency_code);
            }

            // A decrease may never exceed the remaining allocation: otherwise
            // allocated_amount would go negative while the pool is only
            // credited min(newAmount, unspent balance). Reject before any
            // math or pool movement happens.
            if (! $isIncrease
                && $this->mathService->compare($newAmount, $locked->allocated_amount) > 0) {
                throw new AllocationValidationException(
                    "Decrease of {$newAmount} exceeds the allocated amount of {$locked->allocated_amount}"
                );
            }

            $branch = $this->allocationBranchOrFail($locked);

            if ($isIncrease) {
                if (! $this->branchPoolService->allocateToTeller($branch, $locked->currency_code, $newAmount)) {
                    throw new PoolAllocationException('Failed to allocate additional amount from branch pool');
                }
                $locked->current_balance = $this->mathService->add($locked->current_balance, $newAmount);
                $locked->allocated_amount = $this->mathService->add($locked->allocated_amount, $newAmount);
            } else {
                // Only physically unspent float may return to the branch pool.
                // Amounts already sold were paid out to customers; crediting
                // them back would mint phantom funds. The allocated_amount
                // reduction below is pure bookkeeping and independent of the
                // pool return.
                $unspentBalance = $this->mathService->compare($locked->current_balance, '0') > 0
                    ? $locked->current_balance
                    : '0';
                $returnAmount = $this->mathService->compare($newAmount, $unspentBalance) < 0 ? $newAmount : $unspentBalance;

                if ($this->mathService->compare($returnAmount, '0') > 0) {
                    $this->branchPoolService->deallocateFromTeller($branch, $locked->currency_code, $returnAmount);
                    $locked->current_balance = $this->mathService->subtract($locked->current_balance, $returnAmount);
                }

                $locked->allocated_amount = $this->mathService->subtract($locked->allocated_amount, $newAmount);
            }

            $locked->save();

            return $locked;
        });
    }

    public function rejectAllocation(TellerAllocation $allocation, User $rejector, ?string $reason = null): TellerAllocation
    {
        if (! $allocation->isPending()) {
            throw new InvalidAllocationStateException(TellerAllocationStatus::PENDING->value);
        }

        return DB::transaction(function () use ($allocation, $rejector, $reason) {
            // A PENDING allocation never drew funds: the branch pool is only
            // debited in approveAllocation(). Deallocating here would credit
            // the pool with phantom funds taken off other tellers' floats.
            // Funded reversals are handled by returnToPool() (ACTIVE) and
            // modifyAllocation() (APPROVED/ACTIVE) instead.
            $allocation->reject($rejector, $reason);

            return $allocation;
        });
    }

    public function returnToPool(TellerAllocation $allocation): TellerAllocation
    {
        return DB::transaction(function () use ($allocation) {
            $locked = TellerAllocation::where('id', $allocation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status->value !== TellerAllocationStatus::ACTIVE->value) {
                throw new InvalidAllocationStateException(TellerAllocationStatus::ACTIVE->value);
            }

            $returnAmount = $locked->current_balance;

            if ($this->mathService->compare($returnAmount, '0') > 0) {
                $this->branchPoolService->deallocateFromTeller($this->allocationBranchOrFail($locked), $locked->currency_code, $returnAmount);
            }

            $locked->returnToPool();

            return $locked;
        });
    }

    public function forceReturnAllOpen(): int
    {
        $openAllocations = TellerAllocation::where('status', TellerAllocationStatus::ACTIVE->value)
            ->whereDate('session_date', '<', now()->toDateString())
            ->get();

        foreach ($openAllocations as $allocation) {
            $this->returnToPool($allocation);
            $allocation->forceReturn();
        }

        return $openAllocations->count();
    }

    public function getActiveAllocation(User $teller, string $currencyCode): ?TellerAllocation
    {
        return TellerAllocation::where('user_id', $teller->id)
            ->where('currency_code', $currencyCode)
            ->where('status', TellerAllocationStatus::ACTIVE->value)
            ->whereDate('session_date', now()->toDateString())
            ->first();
    }

    public function getPendingAllocationsForBranch(Branch $branch): Collection
    {
        return TellerAllocation::where('branch_id', $branch->id)
            ->where('status', TellerAllocationStatus::PENDING->value)
            ->whereDate('session_date', now()->toDateString())
            ->with('user')
            ->get();
    }

    public function getActiveAllocationsForBranch(Branch $branch): Collection
    {
        return TellerAllocation::where('branch_id', $branch->id)
            ->where('status', TellerAllocationStatus::ACTIVE->value)
            ->whereDate('session_date', now()->toDateString())
            ->with('user')
            ->get();
    }

    public function transferToTeller(TellerAllocation $allocation, User $toTeller): TellerAllocation
    {
        $allocation->update([
            'user_id' => $toTeller->id,
        ]);

        return $allocation;
    }

    public function validateTransaction(User $teller, string $currencyCode, string $amountMyr, bool $isBuy, ?string $amountForeign = null): AllocationValidationResult
    {
        $allocation = $this->getActiveAllocation($teller, $currencyCode);

        if (! $allocation) {
            return new AllocationValidationResult(valid: false, reason: 'No active allocation for this currency');
        }

        // Buying foreign currency ADDS to the teller's foreign float, so there is
        // no foreign balance to check - only the daily MYR turnover limit applies.
        // (Comparing the foreign float against the MYR amount was a unit mismatch
        // that wrongly rejected buys larger than the foreign float.)
        if ($isBuy) {
            if (! $allocation->hasDailyLimitRemaining($amountMyr)) {
                return new AllocationValidationResult(valid: false, reason: 'Daily limit exceeded');
            }

            return new AllocationValidationResult(valid: true, allocation: $allocation);
        }

        // Selling: the teller hands over foreign currency from their allocated float.
        $checkAmount = $amountForeign ?? $amountMyr;

        if (! $allocation->hasAvailable($checkAmount)) {
            return new AllocationValidationResult(valid: false, reason: "No {$allocation->currency_code} balance available to sell");
        }

        if (! $allocation->hasDailyLimitRemaining($amountMyr)) {
            return new AllocationValidationResult(valid: false, reason: 'Daily limit exceeded');
        }

        return new AllocationValidationResult(valid: true, allocation: $allocation);
    }

    /**
     * Check if user has permission to approve/reject allocations.
     */
    public function canManageAllocations(User $user): bool
    {
        return $user->role->isManager() || $user->role->isAdmin();
    }

    public function applyTransactionAllocation(Transaction $transaction, ?TellerAllocation $allocation = null): void
    {
        if ($allocation === null) {
            $user = User::find($transaction->user_id);

            if (! $user || ! $user->isTeller()) {
                return;
            }

            $allocation = $this->getActiveAllocation($user, $transaction->currency_code);
        }

        if (! $allocation) {
            return;
        }

        DB::transaction(function () use ($allocation, $transaction) {
            $lockedAllocation = TellerAllocation::where('id', $allocation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->type === TransactionType::Buy) {
                $lockedAllocation->add((string) $transaction->amount_foreign);
            } else {
                $lockedAllocation->deduct((string) $transaction->amount_foreign);
            }

            $lockedAllocation->addDailyUsed((string) $transaction->amount_local);
        });
    }

    public function reverseTransactionAllocation(Transaction $transaction): void
    {
        $user = User::find($transaction->user_id);

        if (! $user || ! $user->isTeller()) {
            return;
        }

        $allocation = $this->getActiveAllocation($user, $transaction->currency_code);

        if (! $allocation) {
            return;
        }

        DB::transaction(function () use ($allocation, $transaction) {
            $lockedAllocation = TellerAllocation::where('id', $allocation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->type === TransactionType::Buy) {
                $lockedAllocation->deduct((string) $transaction->amount_foreign);
            } else {
                $lockedAllocation->add((string) $transaction->amount_foreign);
            }

            $lockedAllocation->subtractDailyUsed((string) $transaction->amount_local);
        });
    }

    /**
     * Get active allocation for a teller with currency validation.
     *
     * @return array Result with allocation or error
     */
    public function getActiveAllocationForTeller(User $teller, string $currencyCode): array
    {
        $allocation = $this->getActiveAllocation($teller, $currencyCode);

        if (! $allocation) {
            return [
                'success' => true,
                'data' => null,
                'message' => 'No active allocation found',
            ];
        }

        return [
            'success' => true,
            'data' => $allocation,
            'message' => null,
        ];
    }

    /**
     * Resolve the branch of an allocation, failing closed when the relation
     * is missing (e.g. the branch was deleted mid-workflow).
     */
    private function allocationBranchOrFail(TellerAllocation $allocation): Branch
    {
        $branch = $allocation->branch;

        if (! $branch instanceof Branch) {
            throw new TellerBranchRequiredException;
        }

        return $branch;
    }
}
