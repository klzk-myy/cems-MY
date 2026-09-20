<?php

namespace App\Services\Accounting;

use App\Enums\CounterSessionStatus;
use App\Enums\StockReservationStatus;
use App\Enums\TransactionType;
use App\Exceptions\Domain\InsufficientStockException;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\StockReservation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Contracts\CurrencyPositionServiceInterface;
use App\Services\System\CacheInvalidationService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\ValueObjects\QuoteConvention;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CurrencyPositionService implements CurrencyPositionServiceInterface
{
    /**
     * Math service instance for high-precision calculations.
     */
    protected MathService $mathService;

    /**
     * Lock service instance for pessimistic position locking.
     */
    protected CurrencyPositionLockService $lockService;

    protected CacheInvalidationService $cacheInvalidationService;

    protected ThresholdService $thresholdService;

    /**
     * Money-column precision — total_cost, current_value, and
     * unrealized_gain_loss are decimal(18,4).
     */
    private const POSITION_SCALE = 4;

    /**
     * Create a new CurrencyPositionService instance.
     *
     * @param  MathService  $mathService  Math service for high-precision calculations
     * @param  CurrencyPositionLockService  $lockService  Lock service for pessimistic position locking
     */
    public function __construct(
        MathService $mathService,
        CurrencyPositionLockService $lockService,
        CacheInvalidationService $cacheInvalidationService,
        ThresholdService $thresholdService
    ) {
        $this->mathService = $mathService;
        $this->lockService = $lockService;
        $this->cacheInvalidationService = $cacheInvalidationService;
        $this->thresholdService = $thresholdService;
    }

    private function positionPrecision(): int
    {
        return self::POSITION_SCALE;
    }

    /**
     * Precision for the rate columns — average_cost and current_rate are
     * decimal(18,8), matching the per-unit rate convention.
     */
    private function ratePrecision(): int
    {
        return QuoteConvention::PER_UNIT_SCALE;
    }

    /**
     * Update a currency position with a new transaction.
     *
     * Uses MathService for all high-precision calculations.
     * For 'Buy' transactions, increases position and recalculates average cost.
     * For 'Sell' transactions, decreases position (cost basis unchanged).
     *
     * @param  string  $currencyCode  Currency code (e.g., 'USD', 'EUR')
     * @param  string  $quantity  Transaction amount as string
     * @param  string  $rate  Exchange rate for this transaction
     * @param  string  $type  Transaction type: 'Buy' or 'Sell'
     * @param  string|null  $branchId  Owning branch id; null tracks the company-wide position
     * @return CurrencyPosition Updated position model
     *
     * @throws \InvalidArgumentException If selling with insufficient or zero balance
     */
    public function updatePosition(
        string $currencyCode,
        string $quantity,
        string $rate,
        string $type,
        ?string $branchId = null,
        ?Transaction $snapshotFor = null,
    ): CurrencyPosition {
        $position = DB::transaction(function () use ($currencyCode, $quantity, $rate, $type, $branchId, $snapshotFor) {
            if ($type === TransactionType::Buy->value) {
                // Buying foreign currency - lock or create the position
                $position = $this->lockService->lock($branchId, $currencyCode);

                $oldBalance = $position->quantity;
                $oldAvgCost = $position->average_cost;

                if ($this->mathService->compare($oldBalance, '0') > 0) {
                    $newAvgCost = $this->mathService->calculateAverageCost(
                        $oldBalance,
                        $oldAvgCost,
                        $quantity,
                        $rate
                    );
                } else {
                    $newAvgCost = $rate;
                }

                $position = $this->lockService->adjust($position, $quantity, 'add');
            } else {
                // Selling foreign currency - only lock an existing position; do not
                // create a zero-quantity row when no position exists.
                $position = $this->lockService->findForUpdate($branchId, $currencyCode);

                if ($position === null || $this->mathService->compare($position->quantity, '0') <= 0) {
                    throw new InsufficientStockException(
                        $currencyCode,
                        $quantity,
                        $position === null ? '0' : (string) $position->quantity
                    );
                }

                if ($this->mathService->compare($position->quantity, $quantity) < 0) {
                    throw new InsufficientStockException(
                        $currencyCode,
                        $quantity,
                        (string) $position->quantity
                    );
                }

                $oldAvgCost = $position->average_cost;
                $newAvgCost = $oldAvgCost; // Cost basis doesn't change on sale
                $oldBalance = $position->quantity;

                $position = $this->lockService->adjust($position, $quantity, 'subtract');
            }

            if ($snapshotFor !== null) {
                // Snapshot the pre-mutation position state so a later reversal
                // can restore the exact cost basis (plan §1.3).
                $snapshotFor->prev_quantity = $oldBalance;
                $snapshotFor->prev_average_cost = $oldAvgCost;
                $snapshotFor->save();
            }

            $newBalance = $position->quantity;

            $roundedAvgCost = $this->mathService->round($newAvgCost, $this->ratePrecision());
            $roundedRate = $this->mathService->round($rate, $this->ratePrecision());

            $position->update([
                'average_cost' => $roundedAvgCost,
                'current_rate' => $roundedRate,
                'total_cost' => $this->mathService->round(
                    $this->mathService->multiply((string) $newBalance, (string) $roundedAvgCost),
                    $this->positionPrecision()
                ),
                'current_value' => $this->mathService->round(
                    $this->mathService->multiply((string) $newBalance, (string) $roundedRate),
                    $this->positionPrecision()
                ),
                'unrealized_gain_loss' => $this->mathService->round(
                    $this->mathService->calculateRevaluationPnl($newBalance, $roundedAvgCost, $roundedRate),
                    $this->positionPrecision()
                ),
                'last_revalued_at' => now(),
            ]);

            return $position->fresh();
        });

        // Invalidate cache for available balance
        $this->cacheInvalidationService->forgetPosition($branchId, $currencyCode);

        return $position;
    }

    /**
     * Move stock between branches for a stock transfer.
     *
     * 'subtract' (source dispatch leg) mirrors the Sell side of updatePosition:
     * quantity leaves at the position's existing cost basis — average_cost and
     * current_rate are untouched. Pending sell reservations are subtracted from
     * what is dispatchable, so stock earmarked for a pending Sell cannot be
     * transferred away and strand the reservation.
     *
     * 'add' (destination receive/complete/cancel-return leg) mirrors the Buy
     * side: incoming quantity blends into average_cost at the transfer item's
     * cost basis (the source position's average_cost captured at request
     * time). A fresh or unpriced position also adopts that cost basis as its
     * current_rate so unrealized P&L starts at zero rather than showing a
     * phantom loss against a zero market rate.
     *
     * All derived money columns (total_cost, current_value,
     * unrealized_gain_loss, last_revalued_at) are recomputed exactly like
     * updatePosition() — raw quantity writes left them describing the
     * pre-transfer quantity.
     *
     * @param  string|null  $branchId  Position branch key; null is the company-wide row
     * @param  string  $quantity  Quantity moved, as string
     * @param  string  $direction  'add' or 'subtract'
     * @param  string|null  $costBasisRate  Per-unit MYR cost basis for 'add' legs
     * @return CurrencyPosition The updated position
     *
     * @throws InsufficientStockException If the decrement would consume reserved stock
     */
    public function adjustForTransfer(
        ?string $branchId,
        string $currencyCode,
        string $quantity,
        string $direction,
        ?string $costBasisRate = null,
    ): CurrencyPosition {
        $position = DB::transaction(function () use ($branchId, $currencyCode, $quantity, $direction, $costBasisRate) {
            if ($direction === 'subtract') {
                $position = $this->lockService->findForUpdate($branchId, $currencyCode);
                $onHand = $position !== null ? (string) $position->quantity : '0';

                // Dispatchable = on-hand minus stock already promised to
                // pending Sell transactions on this branch.
                $reserved = StockReservation::where('currency_code', $currencyCode)
                    ->where('branch_id', $branchId)
                    ->where('status', StockReservationStatus::Pending)
                    ->where('expires_at', '>', now())
                    ->lockForUpdate()
                    ->sum('quantity');

                $dispatchable = $this->mathService->subtract($onHand, (string) $reserved);

                if ($this->mathService->compare($dispatchable, $quantity) < 0) {
                    throw new InsufficientStockException($currencyCode, $quantity, $dispatchable);
                }

                $position = $this->lockService->adjust($position, $quantity, 'subtract');
                $newAvgCost = (string) ($position->average_cost ?? '0');
            } elseif ($direction === 'add') {
                $position = $this->lockService->lock($branchId, $currencyCode);
                $rate = $costBasisRate ?? (string) ($position->average_cost ?? '0');
                $oldBalance = (string) $position->quantity;

                $newAvgCost = $this->mathService->compare($oldBalance, '0') > 0
                    ? $this->mathService->calculateAverageCost(
                        $oldBalance,
                        (string) ($position->average_cost ?? '0'),
                        $quantity,
                        $rate
                    )
                    : $rate;

                $position = $this->lockService->adjust($position, $quantity, 'add');
            } else {
                throw new \InvalidArgumentException("Unknown transfer direction: {$direction}");
            }

            $newBalance = (string) $position->quantity;
            $roundedAvgCost = $this->mathService->round($newAvgCost, $this->ratePrecision());

            // A transfer is a relocation, not a market observation: keep the
            // position's current_rate. Exception — a fresh/unpriced position
            // adopts the incoming cost basis so it isn't marked at zero.
            $currentRate = (string) ($position->current_rate ?? '0');
            if ($direction === 'add' && $this->mathService->compare($currentRate, '0') <= 0) {
                $currentRate = $roundedAvgCost;
            }
            $roundedRate = $this->mathService->round($currentRate, $this->ratePrecision());

            $position->update([
                'average_cost' => $roundedAvgCost,
                'current_rate' => $roundedRate,
                'total_cost' => $this->mathService->round(
                    $this->mathService->multiply($newBalance, $roundedAvgCost),
                    $this->positionPrecision()
                ),
                'current_value' => $this->mathService->round(
                    $this->mathService->multiply($newBalance, $roundedRate),
                    $this->positionPrecision()
                ),
                'unrealized_gain_loss' => $this->mathService->round(
                    $this->mathService->calculateRevaluationPnl($newBalance, $roundedAvgCost, $roundedRate),
                    $this->positionPrecision()
                ),
                'last_revalued_at' => now(),
            ]);

            return $position->fresh();
        });

        $this->cacheInvalidationService->forgetPosition($branchId, $currencyCode);

        return $position;
    }

    /**
     * Reverse the position impact of a transaction (release/cancellation).
     *
     * Sign convention mirrors updatePosition: a Buy added quantity to
     * the position, so reversing it subtracts the same amount; a Sell removed
     * it, so reversing it adds it back.
     *
     * Average-cost handling: reversing a Sell leaves the cost basis untouched
     * (mirroring updatePosition's sell behaviour, which does not change it).
     * Reversing a Buy restores the pre-buy weighted average by algebraically
     * removing this buy's contribution:
     *
     *     avg_before = (avg_after * qty_after - rate * amount) / (qty_after - amount)
     *
     * This is exact when no other Buy intervened; otherwise it is the best
     * available restoration. If the result would be negative or the position
     * is emptied, the cost basis falls back to zero/unchanged rather than
     * drifting further. No insufficient-balance guard is applied: a reversal
     * is compensating and must not hard-fail the cancellation.
     */
    public function reversePositions(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $position = $this->lockService->findForUpdate(
                $transaction->branch_id !== null ? (string) $transaction->branch_id : null,
                $transaction->currency_code
            );

            if ($position === null) {
                return;
            }

            $isBuyReversal = $transaction->type === TransactionType::Buy;
            $quantity = (string) $transaction->quantity;

            $qtyBeforeAdjust = $position->quantity;
            $avgBeforeAdjust = $position->average_cost;

            $direction = $isBuyReversal ? 'subtract' : 'add';
            $position = $this->lockService->adjust($position, $quantity, $direction);

            $newBalance = $position->quantity;
            $restoredAvgCost = $avgBeforeAdjust;

            // Exact cost-basis restore (plan §1.3): when the forward mutation
            // recorded a snapshot and the reversal lands the position exactly
            // back at the pre-mutation quantity, restore the snapshot verbatim.
            // On any drift (intervening trades), fall through to the algebraic
            // reconstruction below.
            $snapshotApplies = $isBuyReversal
                && $transaction->prev_quantity !== null
                && $transaction->prev_average_cost !== null
                && $this->mathService->compare($newBalance, (string) $transaction->prev_quantity) === 0;

            if ($snapshotApplies) {
                $restoredAvgCost = (string) $transaction->prev_average_cost;
            }

            if ($isBuyReversal && ! $snapshotApplies) {
                if ($this->mathService->compare($newBalance, '0') > 0) {
                    $numerator = $this->mathService->subtract(
                        $this->mathService->multiply($avgBeforeAdjust, $qtyBeforeAdjust),
                        $this->mathService->multiply((string) $transaction->rate, $quantity)
                    );
                    $candidate = $this->mathService->divide($numerator, $newBalance);

                    // Fall back to the current average when intervening buys at
                    // other rates make the algebraic restoration incoherent.
                    $restoredAvgCost = $this->mathService->compare($candidate, '0') >= 0
                        ? $candidate
                        : $avgBeforeAdjust;
                } else {
                    // Position emptied by the reversal: no residual cost basis.
                    $restoredAvgCost = '0';
                }
            }

            $roundedAvgCost = $this->mathService->round($restoredAvgCost, $this->ratePrecision());
            $roundedRate = $this->mathService->round($position->current_rate ?? $restoredAvgCost, $this->ratePrecision());

            // Maintain the same derived columns updatePosition() maintains —
            // leaving them stale made total_cost/current_value describe the
            // pre-reversal quantity.
            $position->update([
                'average_cost' => $roundedAvgCost,
                'total_cost' => $this->mathService->round(
                    $this->mathService->multiply((string) $newBalance, (string) $roundedAvgCost),
                    $this->positionPrecision()
                ),
                'current_value' => $this->mathService->round(
                    $this->mathService->multiply((string) $newBalance, (string) $roundedRate),
                    $this->positionPrecision()
                ),
                'unrealized_gain_loss' => $this->mathService->round(
                    $this->mathService->calculateRevaluationPnl($newBalance, $roundedAvgCost, $roundedRate),
                    $this->positionPrecision()
                ),
                'last_revalued_at' => now(),
            ]);
        });

        // Invalidate cache for available balance
        $this->cacheInvalidationService->forgetPosition(
            $transaction->branch_id !== null ? (string) $transaction->branch_id : null,
            $transaction->currency_code
        );
    }

    public function getOrCreatePosition(int $branchId, string $currencyCode, string $rate): CurrencyPosition
    {
        return DB::transaction(function () use ($branchId, $currencyCode, $rate) {
            $position = $this->lockService->lock((string) $branchId, $currencyCode);

            if ($position->wasRecentlyCreated) {
                $position->update([
                    'average_cost' => $rate,
                    'current_rate' => $rate,
                ]);
                $position->refresh();
            }

            return $position;
        });
    }

    /**
     * Get a specific currency position with pessimistic lock for safe concurrent access.
     *
     * This method should be used when you need to check position balance before
     * making changes, to prevent race conditions where two transactions could
     * both pass the balance check and cause negative positions.
     *
     * @param  string  $currencyCode  Currency code (e.g., 'USD', 'EUR')
     * @param  string  $branchId  Branch identifier
     * @return CurrencyPosition|null Position model or null if not found
     */
    public function getPositionWithLock(string $currencyCode, string $branchId): ?CurrencyPosition
    {
        return $this->lockService->findForUpdate($branchId, $currencyCode);
    }

    /**
     * Get a specific currency position.
     *
     * @param  string  $currencyCode  Currency code (e.g., 'USD', 'EUR')
     * @param  string|null  $branchId  Branch identifier (required)
     * @return CurrencyPosition|null Position model or null if not found
     *
     * @throws \InvalidArgumentException If branch_id is null or empty
     */
    public function getPosition(string $currencyCode, ?string $branchId = null): ?CurrencyPosition
    {
        if ($branchId === null || $branchId === '') {
            throw new \InvalidArgumentException(
                'branch_id is required for position lookup. Transaction must specify a branch.'
            );
        }

        return CurrencyPosition::where('currency_code', $currencyCode)
            ->where('branch_id', $branchId)
            ->first();
    }

    /**
     * Get position for a specific transaction (required branch_id).
     *
     * @param  string  $currencyCode  Currency code (e.g., 'USD', 'EUR')
     * @param  string  $branchId  Branch identifier (required)
     * @return CurrencyPosition|null Position model or null if not found
     *
     * @throws \InvalidArgumentException If branch_id is empty or invalid
     */
    public function getPositionForTransaction(string $currencyCode, string $branchId): ?CurrencyPosition
    {
        if (empty($branchId) || $branchId === 'undefined') {
            throw new \InvalidArgumentException(
                'branch_id is required for position lookup. Transaction must specify a branch.'
            );
        }

        return $this->getPosition($currencyCode, $branchId);
    }

    /**
     * Get all positions for a specific branch.
     *
     * @param  string|null  $branchId  Branch identifier; null returns the company-wide positions
     * @return Collection Collection of position models
     */
    public function getAllPositions(?string $branchId = null): Collection
    {
        return CurrencyPosition::when(
            $branchId === null,
            fn ($query) => $query->whereNull('branch_id'),
            fn ($query) => $query->where('branch_id', $branchId)
        )
            ->with('currency')
            ->get();
    }

    /**
     * Calculate total unrealized P&L across all positions for a branch.
     *
     * Uses MathService for high-precision addition of position P&L values.
     *
     * @param  string|null  $branchId  Branch identifier; null sums the company-wide positions
     * @return string Total unrealized P&L as string
     */
    public function getTotalPnl(?string $branchId = null): string
    {
        $positions = $this->getAllPositions($branchId);
        $totalUnrealized = '0';

        foreach ($positions as $position) {
            $totalUnrealized = $this->mathService->add($totalUnrealized, $position['unrealized_gain_loss'] ?? '0');
        }

        return $totalUnrealized;
    }

    /**
     * Get all currency positions visible to the given user.
     *
     * - Admin: sees consolidated positions (same currency aggregated across all branches)
     * - Compliance Officer: sees all positions (no consolidation)
     * - Manager: sees only their own branch's positions
     * - Teller: sees only positions for their currently open counter session
     */
    /**
     * @return Collection<int, CurrencyPosition>
     */
    public function getVisiblePositionsForUser(User $user): Collection
    {
        // Admin: consolidated view across all branches
        if ($user->role->canManageAllBranches()) {
            return $this->getConsolidatedPositions();
        }

        // Compliance: sees all positions
        if ($user->role->isComplianceOfficer()) {
            return CurrencyPosition::with('currency')->get();
        }

        // Manager: sees only own branch
        if ($user->role->isManager()) {
            return CurrencyPosition::with('currency')
                ->where('branch_id', $user->branch_id)
                ->get();
        }

        // Teller: sees only their open counter session
        $activeSession = CounterSession::where('user_id', $user->id)
            ->where('status', CounterSessionStatus::Open)
            ->first();

        if ($activeSession) {
            // Positions are keyed by branch_id, not by counter code — looking
            // up with the counter code (e.g. 'C01') always returned empty.
            $activeSession->loadMissing('counter');

            return $this->getAllPositions(
                $activeSession->counter?->branch_id !== null
                    ? (string) $activeSession->counter->branch_id
                    : null
            );
        }

        return new Collection;
    }

    /**
     * Get consolidated positions aggregated by currency code across all branches.
     *
     * For Admin dashboard view - shows total of each currency across all branches.
     * Uses weighted average for average_cost and sums unrealized_gain_loss.
     */
    /**
     * @return Collection<int, CurrencyPosition>
     */
    protected function getConsolidatedPositions(): Collection
    {
        // Aggregate per currency in SQL so we fetch one row per currency instead of
        // the full positions table, then doing a PHP-side groupBy on the dashboard path.
        /** @var \Illuminate\Support\Collection<int, object{currency_code:string, total_quantity:?string, total_value:?string, total_unrealized_gain_loss:?string, last_revalued_at:?string, latest_current_rate:?string}> $rows */
        $rows = CurrencyPosition::query()
            ->selectRaw('currency_code')
            ->selectRaw('SUM(quantity) AS total_quantity')
            ->selectRaw('SUM(quantity * average_cost) AS total_value')
            ->selectRaw('SUM(unrealized_gain_loss) AS total_unrealized_gain_loss')
            ->selectRaw('MAX(last_revalued_at) AS last_revalued_at')
            ->selectRaw(
                '(SELECT cp2.current_rate FROM currency_positions cp2 '
                .'WHERE cp2.currency_code = currency_positions.currency_code '
                .'ORDER BY (cp2.last_revalued_at IS NULL) ASC, cp2.last_revalued_at DESC, cp2.id DESC LIMIT 1) AS latest_current_rate'
            )
            ->groupBy('currency_code')
            ->get();

        if ($rows->isEmpty()) {
            return new Collection;
        }

        $currencyCodes = $rows->pluck('currency_code')->all();
        $currencies = Currency::whereIn('code', $currencyCodes)->get()->keyBy('code');

        $consolidated = $rows->map(function ($row) use ($currencies) {
            $totalQuantity = (string) ($row->total_quantity ?? 0);
            $totalValue = (string) ($row->total_value ?? 0);

            // Weighted average cost = total value / total quantity (BCMath).
            $weightedAvgCost = $this->mathService->compare($totalQuantity, '0') !== 0
                ? $this->mathService->divide($totalValue, $totalQuantity)
                : '0';

            $position = new CurrencyPosition([
                'currency_code' => $row->currency_code,
                'branch_id' => null, // Indicates consolidated across branches
                'quantity' => $totalQuantity,
                'average_cost' => $weightedAvgCost,
                'current_rate' => $row->latest_current_rate,
                'unrealized_gain_loss' => (string) ($row->total_unrealized_gain_loss ?? 0),
                'last_revalued_at' => $row->last_revalued_at,
            ]);
            $position->setRelation('currency', $currencies->get($row->currency_code));
            $position->setAttribute('is_consolidated', true);

            return $position;
        });

        return new Collection($consolidated->values());
    }

    /**
     * Aggregate currency position totals grouped by user role visibility.
     *
     * Returns aggregated totals across all positions visible to the user.
     * Uses MathService for precision-safe calculations.
     */
    public function aggregateForUser(User $user): array
    {
        $positions = $this->getVisiblePositionsForUser($user);

        $aggregates = [
            'total_balance_myr' => '0',
            'total_unrealized_gain_loss' => '0',
            'total_positions' => $positions->count(),
            'currencies' => [],
        ];

        foreach ($positions as $position) {
            $myrEquivalent = $this->mathService->multiply(
                $position->quantity,
                $position->current_rate
            );

            $aggregates['total_balance_myr'] = $this->mathService->add(
                $aggregates['total_balance_myr'],
                $myrEquivalent
            );

            $aggregates['total_unrealized_gain_loss'] = $this->mathService->add(
                $aggregates['total_unrealized_gain_loss'],
                $position->unrealized_gain_loss
            );

            $aggregates['currencies'][] = [
                'currency_code' => $position->currency_code,
                'quantity' => $position->quantity,
                'myr_equivalent' => $myrEquivalent,
                'average_cost' => $position->average_cost,
                'current_rate' => $position->current_rate,
                'unrealized_gain_loss' => $position->unrealized_gain_loss,
            ];
        }

        return $aggregates;
    }

    /**
     * Get available balance excluding pending reservations.
     *
     * Positions are keyed by branch and reservations protect that same
     * branch-level pool, so pending reservations are summed by branch_id —
     * not by till. A till-scoped sum let a pending Sell on one till hide
     * reserved stock from another till's availability check.
     *
     * @param  string  $currencyCode  Currency code
     * @param  string  $branchId  Branch identifier (position + reservation lookup)
     * @return string Available balance as string
     */
    public function getAvailableBalance(string $currencyCode, string $branchId): string
    {
        return DB::transaction(function () use ($currencyCode, $branchId) {
            $position = $this->lockService->findForUpdate($branchId, $currencyCode);
            $quantity = $position ? $position->quantity : '0';

            $reserved = StockReservation::where('currency_code', $currencyCode)
                ->where('branch_id', $branchId)
                ->where('status', StockReservationStatus::Pending)
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->sum('quantity');

            $result = $this->mathService->subtract($quantity, (string) $reserved);

            return $this->mathService->round($result, 6);
        });
    }

    /**
     * Reserve stock for a pending approval transaction.
     *
     * @param  Transaction  $transaction  Transaction to reserve stock for
     * @return StockReservation Created reservation
     */
    public function reserveStock(Transaction $transaction): StockReservation
    {
        if (empty($transaction->currency_code) || strlen($transaction->currency_code) !== 3) {
            throw new \InvalidArgumentException('Invalid currency code for stock reservation');
        }

        if (! $transaction->quantity || $this->mathService->compare((string) $transaction->quantity, '0') <= 0) {
            throw new \InvalidArgumentException('Amount foreign must be positive for stock reservation');
        }

        $reservation = StockReservation::create([
            'transaction_id' => $transaction->id,
            'currency_code' => $transaction->currency_code,
            'branch_id' => $transaction->branch_id,
            'till_id' => $transaction->till_id,
            'quantity' => $transaction->quantity,
            'status' => StockReservationStatus::Pending,
            'expires_at' => now()->addHours(24),
            'created_by' => $transaction->user_id,
        ]);

        $this->cacheInvalidationService->forgetPosition($transaction->branch_id, $transaction->currency_code);

        return $reservation;
    }

    /**
     * Consume an existing stock reservation (called at approval time).
     *
     * @param  int  $transactionId  Transaction ID
     * @return StockReservation|null The consumed reservation or null
     */
    public function consumeStockReservation(int $transactionId): ?StockReservation
    {
        return DB::transaction(function () use ($transactionId) {
            $reservation = StockReservation::where('transaction_id', $transactionId)
                ->where('status', StockReservationStatus::Pending)
                ->lockForUpdate()
                ->first();

            if ($reservation === null) {
                return null;
            }

            // Re-check expiry after acquiring lock to prevent race condition
            if ($reservation->expires_at <= now()) {
                return null;
            }

            $reservation->update(['status' => StockReservationStatus::Consumed]);
            $this->cacheInvalidationService->forgetPosition($reservation->branch_id, $reservation->currency_code);

            return $reservation;
        });
    }

    /**
     * Release a pending stock reservation.
     *
     * @param  int  $transactionId  Transaction ID
     * @return StockReservation|null The released reservation or null
     */
    public function releaseStockReservation(int $transactionId): ?StockReservation
    {
        return DB::transaction(function () use ($transactionId) {
            $reservation = StockReservation::where('transaction_id', $transactionId)
                ->where('status', StockReservationStatus::Pending)
                ->lockForUpdate()
                ->first();

            if ($reservation === null) {
                return null;
            }

            $reservation->update(['status' => StockReservationStatus::Released]);
            $this->cacheInvalidationService->forgetPosition($reservation->branch_id, $reservation->currency_code);

            return $reservation;
        });
    }
}
