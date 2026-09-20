<?php

namespace App\Services\Branch;

use App\Enums\Permission;
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
use App\Models\CounterSession;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditService;
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
        protected AuditService $auditService,
        protected TillService $tillService,
    ) {}

    public function requestAllocation(User $teller, User $approver, string $currencyCode, string $requestedQuantity, ?string $dailyLimitMyr = null, ?Counter $counter = null): TellerAllocation
    {
        $branch = $teller->branch;

        if (! $branch instanceof Branch) {
            throw new TellerBranchRequiredException;
        }

        $pool = $this->branchPoolService->getOrCreateForBranch($branch, $currencyCode);

        if (! $pool->hasAvailable($requestedQuantity)) {
            throw new InsufficientPoolBalanceException($currencyCode, (string) $pool->available_balance, $requestedQuantity);
        }

        $allocationData = [
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'counter_id' => $counter?->id,
            'currency_code' => $currencyCode,
            'requested_quantity' => $requestedQuantity,
            'allocated_quantity' => $requestedQuantity,
            'current_quantity' => 0,
            'daily_used_myr' => 0,
            'status' => TellerAllocationStatus::Pending->value,
            'session_date' => now()->toDateString(),
        ];

        if ($dailyLimitMyr !== null) {
            $allocationData['daily_limit_myr'] = $dailyLimitMyr;
        }

        return DB::transaction(function () use ($allocationData, $teller, $approver, $currencyCode, $requestedQuantity) {
            $allocation = TellerAllocation::create($allocationData);

            $this->auditService->log(
                'teller_allocation_requested',
                $teller->id,
                'TellerAllocation',
                $allocation->id,
                [],
                [
                    'requested_by' => $teller->id,
                    'approver_id' => $approver->id,
                    'currency_code' => $currencyCode,
                    'requested_quantity' => $requestedQuantity,
                ]
            );

            return $allocation;
        });
    }

    public function approveAllocation(TellerAllocation $allocation, User $approver, string $approvedAmount, ?string $dailyLimitMyr = null): TellerAllocation
    {
        return DB::transaction(function () use ($allocation, $approver, $approvedAmount, $dailyLimitMyr) {
            $locked = TellerAllocation::where('id', $allocation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isPending()) {
                throw new InvalidAllocationStateException(TellerAllocationStatus::Pending->value);
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
            throw new InvalidAllocationStateException(TellerAllocationStatus::Approved->value);
        }

        $allocation->activate();

        return $allocation;
    }

    /**
     * Move stock between the teller's allocation (custody) and the open
     * session's till (drawer). Loading bumps the till row's opening_balance
     * so expected-closing math covers it; unloading returns only unspent
     * drawer cash (expected closing) back into custody.
     */
    public function moveBetweenTillAndAllocation(
        TellerAllocation $allocation,
        CounterSession $session,
        string $quantity,
        bool $toTill
    ): TellerAllocation {
        return DB::transaction(function () use ($allocation, $session, $quantity, $toTill) {
            $locked = TellerAllocation::where('id', $allocation->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || ! $locked->isActive()) {
                throw new InvalidAllocationStateException(TellerAllocationStatus::Active->value);
            }

            $till = TillBalance::where('till_id', $session->tillCode())
                ->where('currency_code', $locked->currency_code)
                ->whereDate('date', $session->session_date)
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();

            if ($toTill) {
                if ($this->mathService->compare($quantity, (string) $locked->current_quantity) > 0) {
                    throw new AllocationValidationException(
                        "Load of {$quantity} exceeds allocation balance of {$locked->current_quantity}"
                    );
                }

                if (! $till) {
                    $till = TillBalance::openFor(
                        $session->tillCode(),
                        $locked->currency_code,
                        $session->counter->branch_id ?? $locked->branch_id,
                        '0',
                        $session->session_date,
                        $session->user_id
                    );
                }

                $till->opening_balance = $this->mathService->add((string) $till->opening_balance, $quantity);
                $locked->current_quantity = $this->mathService->subtract((string) $locked->current_quantity, $quantity);
                $locked->loaded_quantity = $this->mathService->add((string) ($locked->loaded_quantity ?? '0'), $quantity);
            } else {
                if (! $till) {
                    throw new AllocationValidationException(
                        "No open till balance for {$locked->currency_code} in this session."
                    );
                }

                $expected = $this->tillService->expectedClosingForBalance($till);

                if ($this->mathService->compare($quantity, $expected) > 0) {
                    throw new AllocationValidationException(
                        "Unload of {$quantity} exceeds till balance of {$expected}"
                    );
                }

                $till->opening_balance = $this->mathService->subtract((string) $till->opening_balance, $quantity);
                $locked->current_quantity = $this->mathService->add((string) $locked->current_quantity, $quantity);
                // Unloading more than was loaded converts plain drawer cash
                // into custody, so the loaded tracker clamps at zero rather
                // than going negative.
                $loaded = (string) ($locked->loaded_quantity ?? '0');
                $locked->loaded_quantity = $this->mathService->compare($quantity, $loaded) < 0
                    ? $this->mathService->subtract($loaded, $quantity)
                    : '0';
            }

            $till->save();
            $locked->save();

            return $locked;
        });
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
            // allocated_quantity would go negative while the pool is only
            // credited min(newAmount, unspent balance). Reject before any
            // math or pool movement happens.
            if (! $isIncrease
                && $this->mathService->compare($newAmount, $locked->allocated_quantity) > 0) {
                throw new AllocationValidationException(
                    "Decrease of {$newAmount} exceeds the allocated amount of {$locked->allocated_quantity}"
                );
            }

            $branch = $this->allocationBranchOrFail($locked);

            if ($isIncrease) {
                if (! $this->branchPoolService->allocateToTeller($branch, $locked->currency_code, $newAmount)) {
                    throw new PoolAllocationException('Failed to allocate additional amount from branch pool');
                }
                $locked->current_quantity = $this->mathService->add($locked->current_quantity, $newAmount);
                $locked->allocated_quantity = $this->mathService->add($locked->allocated_quantity, $newAmount);
            } else {
                // Only physically unspent float may return to the branch pool.
                // Amounts already sold were paid out to customers; crediting
                // them back would mint phantom funds. The allocated_quantity
                // reduction below is pure bookkeeping and independent of the
                // pool return.
                $unspentBalance = $this->mathService->compare($locked->current_quantity, '0') > 0
                    ? $locked->current_quantity
                    : '0';
                $returnAmount = $this->mathService->compare($newAmount, $unspentBalance) < 0 ? $newAmount : $unspentBalance;

                if ($this->mathService->compare($returnAmount, '0') > 0) {
                    $this->branchPoolService->deallocateFromTeller($branch, $locked->currency_code, $returnAmount);
                    $locked->current_quantity = $this->mathService->subtract($locked->current_quantity, $returnAmount);
                }

                $locked->allocated_quantity = $this->mathService->subtract($locked->allocated_quantity, $newAmount);
            }

            // NOTE: allocated_quantity is per-allocation bookkeeping; the pool's
            // allocated_balance tracks live custody — applyTransactionAllocation
            // consumes the earmark on sells and grows it on buys, so the pool
            // earmark always equals current_quantity + loaded_quantity across
            // active allocations plus outstanding approved earmarks. There is
            // deliberately no assert here: current_quantity may exceed
            // allocated_quantity after intraday buys (buy adds float, not allocation).

            $locked->save();

            return $locked;
        });
    }

    public function rejectAllocation(TellerAllocation $allocation, User $rejector, ?string $reason = null): TellerAllocation
    {
        if (! $allocation->isPending()) {
            throw new InvalidAllocationStateException(TellerAllocationStatus::Pending->value);
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

    /**
     * Cancel an allocation that never reached the teller's hands. A pending
     * request drew no pool funds and simply rejects; an approved-but-
     * unaccepted one still holds a pool earmark (approve moved
     * available→allocated), which releases back to available. Used by
     * branch settlement so stale requests neither linger nor block the
     * close. Active custody is returned via returnToPool(), not here.
     */
    public function cancelAllocation(TellerAllocation $allocation, User $actor, ?string $reason = null): TellerAllocation
    {
        return DB::transaction(function () use ($allocation, $actor, $reason) {
            $locked = TellerAllocation::where('id', $allocation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status->value === TellerAllocationStatus::Approved->value) {
                if ($this->mathService->compare((string) $locked->allocated_quantity, '0') > 0) {
                    $this->branchPoolService->deallocateFromTeller($this->allocationBranchOrFail($locked), $locked->currency_code, (string) $locked->allocated_quantity);
                }
            } elseif ($locked->status->value !== TellerAllocationStatus::Pending->value) {
                throw new InvalidAllocationStateException(TellerAllocationStatus::Pending->value);
            }

            $locked->cancel($actor, $reason);

            return $locked;
        });
    }

    public function returnToPool(TellerAllocation $allocation): TellerAllocation
    {
        return DB::transaction(function () use ($allocation) {
            $locked = TellerAllocation::where('id', $allocation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status->value !== TellerAllocationStatus::Active->value) {
                throw new InvalidAllocationStateException(TellerAllocationStatus::Active->value);
            }

            // The pool earmark releases for BOTH the unspent balance and any
            // stock still loaded into a till — once custody returns, loaded
            // cash is ordinary drawer/branch stock again, no longer earmarked
            // to this teller.
            $returnAmount = $this->mathService->add(
                (string) $locked->current_quantity,
                (string) ($locked->loaded_quantity ?? '0')
            );

            if ($this->mathService->compare($returnAmount, '0') > 0) {
                $this->branchPoolService->deallocateFromTeller($this->allocationBranchOrFail($locked), $locked->currency_code, $returnAmount);
            }

            $locked->loaded_quantity = '0';
            $locked->returnToPool();

            return $locked;
        });
    }

    /**
     * A teller may hold several active same-currency allocations for the day
     * (e.g. an earlier allocation depleted but not yet returned to the pool
     * plus a newer replenishment). Order by remaining balance so a funded
     * allocation is preferred; a depleted one is still returned when it is
     * the only active row so validation reports an accurate balance error.
     */
    public function getActiveAllocation(User $teller, string $currencyCode): ?TellerAllocation
    {
        return TellerAllocation::where('user_id', $teller->id)
            ->where('currency_code', $currencyCode)
            ->where('status', TellerAllocationStatus::Active->value)
            ->whereDate('session_date', now()->toDateString())
            ->orderByDesc('current_quantity')
            ->orderByDesc('id')
            ->first();
    }

    public function getPendingAllocationsForBranch(Branch $branch): Collection
    {
        return TellerAllocation::where('branch_id', $branch->id)
            ->where('status', TellerAllocationStatus::Pending->value)
            ->whereDate('session_date', now()->toDateString())
            ->with(TellerAllocation::API_RELATIONS)
            ->get();
    }

    public function getActiveAllocationsForBranch(Branch $branch): Collection
    {
        return TellerAllocation::where('branch_id', $branch->id)
            ->where('status', TellerAllocationStatus::Active->value)
            ->whereDate('session_date', now()->toDateString())
            ->with(TellerAllocation::API_RELATIONS)
            ->get();
    }

    public function transferToTeller(TellerAllocation $allocation, User $toTeller): TellerAllocation
    {
        return DB::transaction(function () use ($allocation, $toTeller) {
            $locked = TellerAllocation::where('id', $allocation->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Target must be a teller in the same branch as the allocation.
            if ($toTeller->branch_id !== $locked->branch_id) {
                throw new AllocationValidationException(
                    'Cannot transfer allocation to a teller in a different branch.'
                );
            }

            if (! $toTeller->isTeller()) {
                throw new AllocationValidationException(
                    'Allocation can only be transferred to a teller.'
                );
            }

            $fromUserId = $locked->user_id;

            $locked->update(['user_id' => $toTeller->id]);

            $this->auditService->log(
                'teller_allocation_transferred',
                $toTeller->id,
                'TellerAllocation',
                $locked->id,
                ['user_id' => $fromUserId],
                ['user_id' => $toTeller->id, 'currency_code' => $locked->currency_code]
            );

            return $locked->refresh();
        });
    }

    public function validateTransaction(User $teller, string $currencyCode, string $amountMyr, bool $isBuy, ?string $quantity = null): AllocationValidationResult
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
        $checkAmount = $quantity ?? $amountMyr;

        if (! $allocation->hasAvailable($checkAmount)) {
            return new AllocationValidationResult(valid: false, reason: "No {$allocation->currency_code} balance available to sell");
        }

        if (! $allocation->hasDailyLimitRemaining($amountMyr)) {
            return new AllocationValidationResult(valid: false, reason: 'Daily limit exceeded');
        }

        return new AllocationValidationResult(valid: true, allocation: $allocation);
    }

    /**
     * Determine the teller allocation to attach to a new transaction.
     *
     * Single source of truth shared by the wizard and the creation service.
     * Non-tellers get no allocation. Buys must validate against the daily
     * limit; sells attach the teller's active allocation for the currency.
     *
     * @param  array{type: string, currency_code: string}  $data  Validated transaction data.
     * @param  string  $amountMyr  Local currency amount as a numeric string.
     *
     * @throws AllocationValidationException When the active allocation cannot cover the transaction.
     */
    public function resolveForTransaction(User $user, array $data, string $amountMyr): ?TellerAllocation
    {
        if (! $user->isTeller()) {
            return null;
        }

        if ($data['type'] === TransactionType::Buy->value) {
            $result = $this->validateTransaction($user, $data['currency_code'], $amountMyr, true);

            if (! $result->valid) {
                throw new AllocationValidationException($result->reason);
            }

            return $result->allocation;
        }

        return $this->getActiveAllocation($user, $data['currency_code']);
    }

    /**
     * Check if user has permission to approve/reject allocations.
     */
    public function canManageAllocations(User $user): bool
    {
        return $user->role->canPerform(Permission::ManageAllocations);
    }

    public function applyTransactionAllocation(Transaction $transaction, ?TellerAllocation $allocation = null): void
    {
        if ($allocation === null) {
            $allocation = $this->resolveTransactionAllocation($transaction);
        }

        if (! $allocation) {
            return;
        }

        DB::transaction(function () use ($allocation, $transaction) {
            $lockedAllocation = TellerAllocation::where('id', $allocation->id)
                ->lockForUpdate()
                ->firstOrFail();

            // The pool earmark tracks live custody: a sell hands stock to the
            // customer (earmark consumed, never returns to available), a buy
            // brings in stock the customer sold to the teller (earmark grows).
            // Without this the spent/earned amounts drift in allocated_balance.
            if ($transaction->type === TransactionType::Buy) {
                $lockedAllocation->add((string) $transaction->quantity);
                $this->branchPoolService->growTellerEarmark(
                    $this->allocationBranchOrFail($lockedAllocation),
                    $lockedAllocation->currency_code,
                    (string) $transaction->quantity
                );
            } else {
                $lockedAllocation->deduct((string) $transaction->quantity);
                $this->branchPoolService->consumeTellerEarmark(
                    $this->allocationBranchOrFail($lockedAllocation),
                    $lockedAllocation->currency_code,
                    (string) $transaction->quantity
                );
            }

            // The cap is enforced inside the row lock (conditional UPDATE), not
            // by the pre-flight hasDailyLimitRemaining() read — a concurrent
            // booking or a stale pending approval can never overshoot it.
            $lockedAllocation->addDailyUsedWithinLimit((string) $transaction->amount_myr);
        });
    }

    public function reverseTransactionAllocation(Transaction $transaction): void
    {
        $allocation = $this->resolveTransactionAllocation($transaction);

        if (! $allocation) {
            return;
        }

        DB::transaction(function () use ($allocation, $transaction) {
            $lockedAllocation = TellerAllocation::where('id', $allocation->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Mirror applyTransactionAllocation: reversing a buy consumes the
            // earmark the buy created; reversing a sell restores it.
            if ($transaction->type === TransactionType::Buy) {
                $lockedAllocation->deduct((string) $transaction->quantity);
                $this->branchPoolService->consumeTellerEarmark(
                    $this->allocationBranchOrFail($lockedAllocation),
                    $lockedAllocation->currency_code,
                    (string) $transaction->quantity
                );
            } else {
                $lockedAllocation->add((string) $transaction->quantity);
                $this->branchPoolService->growTellerEarmark(
                    $this->allocationBranchOrFail($lockedAllocation),
                    $lockedAllocation->currency_code,
                    (string) $transaction->quantity
                );
            }

            $lockedAllocation->subtractDailyUsed((string) $transaction->amount_myr);
        });
    }

    /**
     * Resolve which allocation a transaction's balance effect belongs to:
     * the allocation pinned at creation when it is still usable, otherwise
     * the teller's funded active allocation for the currency. Transactions
     * created before the pinning column existed fall back to the lookup.
     */
    private function resolveTransactionAllocation(Transaction $transaction): ?TellerAllocation
    {
        if ($transaction->teller_allocation_id) {
            $pinned = TellerAllocation::where('id', $transaction->teller_allocation_id)
                ->where('status', TellerAllocationStatus::Active->value)
                ->whereDate('session_date', now()->toDateString())
                ->first();

            if ($pinned) {
                return $pinned;
            }
        }

        $user = User::find($transaction->user_id);

        if (! $user || ! $user->isTeller()) {
            return null;
        }

        return $this->getActiveAllocation($user, $transaction->currency_code);
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
            'data' => $allocation->loadMissing(TellerAllocation::API_RELATIONS),
            'message' => null,
        ];
    }

    /**
     * Resolve the branch of an allocation, failing closed when the relation
     * is missing (e.g. the branch was deleted mid-workflow).
     */
    private function allocationBranchOrFail(TellerAllocation $allocation): Branch
    {
        $branch = $allocation->loadMissing('branch')->branch;

        if (! $branch instanceof Branch) {
            throw new TellerBranchRequiredException;
        }

        return $branch;
    }
}
