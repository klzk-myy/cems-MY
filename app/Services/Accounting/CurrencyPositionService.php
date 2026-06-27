<?php

namespace App\Services\Accounting;

use App\Enums\CounterSessionStatus;
use App\Enums\StockReservationStatus;
use App\Enums\TransactionType;
use App\Models\CounterSession;
use App\Models\CurrencyPosition;
use App\Models\StockReservation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Contracts\CurrencyPositionServiceInterface;
use App\Services\System\MathService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CurrencyPositionService implements CurrencyPositionServiceInterface
{
    /**
     * Math service instance for high-precision calculations.
     */
    protected MathService $mathService;

    /**
     * Precision for position calculations (4 decimals for rates/balances)
     */
    protected int $positionPrecision = 4;

    /**
     * Create a new CurrencyPositionService instance.
     *
     * @param  MathService  $mathService  Math service for high-precision calculations
     */
    public function __construct(MathService $mathService)
    {
        $this->mathService = $mathService;
        $this->positionPrecision = (int) config('thresholds.rates.precision', 4);
    }

    /**
     * Update a currency position with a new transaction.
     *
     * Uses MathService for all high-precision calculations.
     * For 'Buy' transactions, increases position and recalculates average cost.
     * For 'Sell' transactions, decreases position (cost basis unchanged).
     *
     * @param  string  $currencyCode  Currency code (e.g., 'USD', 'EUR')
     * @param  string  $amount  Transaction amount as string
     * @param  string  $rate  Exchange rate for this transaction
     * @param  string  $type  Transaction type: 'Buy' or 'Sell'
     * @param  string  $branchId  Branch identifier (default: 'HQ')
     * @return CurrencyPosition Updated position model
     *
     * @throws \InvalidArgumentException If selling with insufficient or zero balance
     */
    public function updatePosition(
        string $currencyCode,
        string $amount,
        string $rate,
        string $type,
        string $branchId = 'HQ'
    ): CurrencyPosition {
        $position = DB::transaction(function () use ($currencyCode, $amount, $rate, $type, $branchId) {
            // Lock the position row for update to prevent race conditions on concurrent sells
            $position = CurrencyPosition::where('currency_code', $currencyCode)
                ->where('branch_id', $branchId)
                ->lockForUpdate()
                ->first();

            // Create position if it doesn't exist (race-safe)
            if ($position === null) {
                $position = $this->getOrCreatePosition((int) $branchId, $currencyCode, $rate);
            }

            $oldBalance = $position->quantity;
            $oldAvgCost = $position->average_cost;

            if ($type === TransactionType::Buy->value) {
                // Buying foreign currency - increase position
                $newBalance = $this->mathService->add($oldBalance, $amount);
                if ($this->mathService->compare($oldBalance, '0') > 0) {
                    $newAvgCost = $this->mathService->calculateAverageCost(
                        $oldBalance,
                        $oldAvgCost,
                        $amount,
                        $rate
                    );
                } else {
                    $newAvgCost = $rate;
                }
            } else {
                // Selling foreign currency - decrease position
                // Check for sufficient balance - prevent negative positions
                if ($this->mathService->compare($oldBalance, '0') <= 0) {
                    throw new \InvalidArgumentException(
                        'Cannot sell: Position is empty or negative'
                    );
                }
                if ($this->mathService->compare($oldBalance, $amount) < 0) {
                    throw new \InvalidArgumentException(
                        "Insufficient balance. Available: {$oldBalance}, Requested: {$amount}"
                    );
                }
                $newBalance = $this->mathService->subtract($oldBalance, $amount);
                $newAvgCost = $oldAvgCost; // Cost basis doesn't change on sale
            }

            $position->update([
                'quantity' => $this->mathService->round($newBalance, $this->positionPrecision),
                'average_cost' => $this->mathService->round($newAvgCost, $this->positionPrecision),
                'current_rate' => $this->mathService->round($rate, $this->positionPrecision),
                'unrealized_gain_loss' => $this->mathService->round(
                    $this->mathService->calculateRevaluationPnl($newBalance, $newAvgCost, $rate),
                    $this->positionPrecision
                ),
                'last_revalued_at' => now(),
            ]);

            return $position->fresh();
        });

        // Invalidate cache for available balance
        $cacheKey = "position:{$branchId}:{$currencyCode}:available";
        Cache::forget($cacheKey);

        return $position;
    }

    public function getOrCreatePosition(int $branchId, string $currencyCode, string $rate): CurrencyPosition
    {
        return DB::transaction(function () use ($branchId, $currencyCode, $rate) {
            $position = CurrencyPosition::where('currency_code', $currencyCode)
                ->where('branch_id', $branchId)
                ->lockForUpdate()
                ->first();

            if ($position) {
                return $position;
            }

            try {
                return CurrencyPosition::create([
                    'currency_code' => $currencyCode,
                    'branch_id' => $branchId,
                    'quantity' => '0',
                    'average_cost' => $rate,
                    'current_rate' => $rate,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                return CurrencyPosition::where('currency_code', $currencyCode)
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
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
        return CurrencyPosition::where('currency_code', $currencyCode)
            ->where('branch_id', $branchId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Get a specific currency position.
     *
     * @param  string  $currencyCode  Currency code (e.g., 'USD', 'EUR')
     * @param  string|null  $branchId  Branch identifier (default: 'HQ' with warning log)
     * @return CurrencyPosition|null Position model or null if not found
     */
    public function getPosition(string $currencyCode, ?string $branchId = null): ?CurrencyPosition
    {
        // If no branch specified, use HQ as fallback (but log a warning)
        if ($branchId === null) {
            Log::warning(
                'getPosition called without branch_id - using HQ as fallback',
                [
                    'currency_code' => $currencyCode,
                    'stack_trace' => collect(debug_backtrace())->take(5)->pluck('file')->toArray(),
                ]
            );
            $branchId = 'HQ';
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
     * @param  string  $branchId  Branch identifier (default: 'HQ')
     * @return Collection Collection of position models
     */
    public function getAllPositions(string $branchId = 'HQ'): Collection
    {
        return CurrencyPosition::where('branch_id', $branchId)
            ->with('currency')
            ->get();
    }

    /**
     * Calculate total unrealized P&L across all positions for a branch.
     *
     * Uses MathService for high-precision addition of position P&L values.
     *
     * @param  string  $branchId  Branch identifier (default: 'HQ')
     * @return string Total unrealized P&L as string
     */
    public function getTotalPnl(string $branchId = 'HQ'): string
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
            return $this->getAllPositions($activeSession->till_id);
        }

        return collect();
    }

    /**
     * Get consolidated positions aggregated by currency code across all branches.
     *
     * For Admin dashboard view - shows total of each currency across all branches.
     * Uses weighted average for average_cost and sums unrealized_gain_loss.
     */
    protected function getConsolidatedPositions(): Collection
    {
        $positions = CurrencyPosition::with('currency')->get();

        if ($positions->isEmpty()) {
            return new Collection;
        }

        // Group by currency_code and consolidate
        $consolidated = $positions->groupBy('currency_code')->map(function ($group, $currencyCode) {
            $totalQuantity = '0';
            $totalValue = '0';
            $totalUnrealizedGainLoss = '0';
            $firstCurrency = null;

            foreach ($group as $position) {
                $firstCurrency = $firstCurrency ?? $position->currency;
                $totalQuantity = $this->mathService->add($totalQuantity, $position->quantity);
                // Value = quantity * average_cost
                $positionValue = $this->mathService->multiply($position->quantity, $position->average_cost ?? '0');
                $totalValue = $this->mathService->add($totalValue, $positionValue);
                $totalUnrealizedGainLoss = $this->mathService->add($totalUnrealizedGainLoss, $position->unrealized_gain_loss ?? '0');
            }

            // Weighted average cost = total value / total quantity
            $weightedAvgCost = $this->mathService->compare($totalQuantity, '0') !== 0
                ? $this->mathService->divide($totalValue, $totalQuantity)
                : '0';

            // Create a virtual consolidated position
            $consolidatedPosition = new CurrencyPosition([
                'currency_code' => $currencyCode,
                'branch_id' => null, // Indicates consolidated across branches
                'quantity' => $totalQuantity,
                'average_cost' => $weightedAvgCost,
                'current_rate' => $group->first()->current_rate,
                'unrealized_gain_loss' => $totalUnrealizedGainLoss,
                'last_revalued_at' => $group->max('last_revalued_at'),
            ]);
            $consolidatedPosition->setRelation('currency', $firstCurrency);
            $consolidatedPosition->setAttribute('is_consolidated', true);

            return $consolidatedPosition;
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
     * @param  string  $currencyCode  Currency code
     * @param  string  $locationId  Branch identifier (used for position and reservation lookup)
     * @return string Available balance as string
     */
    public function getAvailableBalance(string $currencyCode, string $locationId): string
    {
        return DB::transaction(function () use ($currencyCode, $locationId) {
            $position = CurrencyPosition::where('currency_code', $currencyCode)
                ->where('branch_id', $locationId)
                ->lockForUpdate()
                ->first();
            $quantity = $position ? $position->quantity : '0';

            $reserved = StockReservation::where('currency_code', $currencyCode)
                ->where('till_id', $locationId)
                ->where('status', StockReservationStatus::Pending)
                ->where('expires_at', '>', now())
                ->sum('amount_foreign');

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
        $reservation = StockReservation::create([
            'transaction_id' => $transaction->id,
            'currency_code' => $transaction->currency_code,
            'branch_id' => $transaction->branch_id,
            'till_id' => $transaction->till_id,
            'amount_foreign' => $transaction->amount_foreign,
            'status' => StockReservationStatus::Pending,
            'expires_at' => now()->addHours(24),
            'created_by' => $transaction->user_id,
        ]);

        Cache::forget("position:{$transaction->branch_id}:{$transaction->currency_code}:available");

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
        $reservation = StockReservation::where('transaction_id', $transactionId)
            ->where('status', StockReservationStatus::Pending)
            ->lockForUpdate()
            ->first();

        if ($reservation === null || $reservation->isExpired()) {
            return null;
        }

        $reservation->update(['status' => StockReservationStatus::Consumed]);
        Cache::forget("position:{$reservation->branch_id}:{$reservation->currency_code}:available");

        return $reservation;
    }

    /**
     * Release a pending stock reservation.
     *
     * @param  int  $transactionId  Transaction ID
     * @return StockReservation|null The released reservation or null
     */
    public function releaseStockReservation(int $transactionId): ?StockReservation
    {
        $reservation = StockReservation::where('transaction_id', $transactionId)
            ->where('status', StockReservationStatus::Pending)
            ->lockForUpdate()
            ->first();

        if ($reservation === null) {
            return null;
        }

        $reservation->update(['status' => StockReservationStatus::Released]);
        Cache::forget("position:{$reservation->branch_id}:{$reservation->currency_code}:available");

        return $reservation;
    }
}
