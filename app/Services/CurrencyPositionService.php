<?php

namespace App\Services;

use App\Enums\CounterSessionStatus;
use App\Enums\StockReservationStatus;
use App\Enums\TransactionType;
use App\Models\CounterSession;
use App\Models\CurrencyPosition;
use App\Models\StockReservation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Currency Position Service
 *
 * Manages foreign currency positions for currency exchange operations.
 * Tracks balances, calculates average costs, and monitors unrealized P&L.
 * Uses MathService for high-precision calculations to prevent floating-point errors.
 */
class CurrencyPositionService
{
    /**
     * Math service instance for high-precision calculations.
     */
    protected MathService $mathService;

    /**
     * Create a new CurrencyPositionService instance.
     *
     * @param  MathService  $mathService  Math service for high-precision calculations
     */
    public function __construct(MathService $mathService)
    {
        $this->mathService = $mathService;
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
     * @param  string  $tillId  Till identifier (default: 'MAIN')
     * @return CurrencyPosition Updated position model
     *
     * @throws \InvalidArgumentException If selling with insufficient or zero balance
     */
    public function updatePosition(
        string $currencyCode,
        string $amount,
        string $rate,
        string $type,
        string $tillId = 'MAIN'
    ): CurrencyPosition {
        return DB::transaction(function () use ($currencyCode, $amount, $rate, $type, $tillId) {
            // Lock the position row for update to prevent race conditions on concurrent sells
            $position = CurrencyPosition::where('currency_code', $currencyCode)
                ->where('till_id', $tillId)
                ->lockForUpdate()
                ->first();

            // Create position if it doesn't exist
            if ($position === null) {
                $position = new CurrencyPosition([
                    'currency_code' => $currencyCode,
                    'till_id' => $tillId,
                    'balance' => '0',
                    'avg_cost_rate' => $rate,
                    'last_valuation_rate' => $rate,
                ]);
                $position->save();
            }

            $oldBalance = $position->balance;
            $oldAvgCost = $position->avg_cost_rate;

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
                'balance' => $newBalance,
                'avg_cost_rate' => $newAvgCost,
                'last_valuation_rate' => $rate,
                'unrealized_pnl' => $this->mathService->calculateRevaluationPnl($newBalance, $newAvgCost, $rate),
                'last_valuation_at' => now(),
            ]);

            return $position->fresh();
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
     * @param  string  $tillId  Till identifier
     * @return CurrencyPosition|null Position model or null if not found
     */
    public function getPositionWithLock(string $currencyCode, string $tillId): ?CurrencyPosition
    {
        return CurrencyPosition::where('currency_code', $currencyCode)
            ->where('till_id', $tillId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Get a specific currency position.
     *
     * @param  string  $currencyCode  Currency code (e.g., 'USD', 'EUR')
     * @param  string|null  $tillId  Till identifier (default: 'MAIN' with warning log)
     * @return CurrencyPosition|null Position model or null if not found
     */
    public function getPosition(string $currencyCode, ?string $tillId = null): ?CurrencyPosition
    {
        // If no till specified, use MAIN as fallback (but log a warning)
        if ($tillId === null) {
            Log::warning(
                'getPosition called without till_id - using MAIN as fallback',
                [
                    'currency_code' => $currencyCode,
                    'stack_trace' => collect(debug_backtrace())->take(5)->pluck('file')->toArray(),
                ]
            );
            $tillId = 'MAIN';
        }

        return CurrencyPosition::where('currency_code', $currencyCode)
            ->where('till_id', $tillId)
            ->first();
    }

    /**
     * Get position for a specific transaction (required till_id).
     *
     * @param  string  $currencyCode  Currency code (e.g., 'USD', 'EUR')
     * @param  string  $tillId  Till identifier (required)
     * @return CurrencyPosition|null Position model or null if not found
     *
     * @throws \InvalidArgumentException If till_id is empty or invalid
     */
    public function getPositionForTransaction(string $currencyCode, string $tillId): ?CurrencyPosition
    {
        if (empty($tillId) || $tillId === 'undefined') {
            throw new \InvalidArgumentException(
                'till_id is required for position lookup. Transaction must specify a till.'
            );
        }

        return $this->getPosition($currencyCode, $tillId);
    }

    /**
     * Get all positions for a specific till.
     *
     * @param  string  $tillId  Till identifier (default: 'MAIN')
     * @return Collection Collection of position models
     */
    public function getAllPositions(string $tillId = 'MAIN'): Collection
    {
        return CurrencyPosition::where('till_id', $tillId)
            ->with('currency')
            ->get();
    }

    /**
     * Calculate total unrealized P&L across all positions for a till.
     *
     * Uses MathService for high-precision addition of position P&L values.
     *
     * @param  string  $tillId  Till identifier (default: 'MAIN')
     * @return string Total unrealized P&L as string
     */
    public function getTotalPnl(string $tillId = 'MAIN'): string
    {
        $positions = $this->getAllPositions($tillId);
        $totalUnrealized = '0';

        foreach ($positions as $position) {
            $totalUnrealized = $this->mathService->add($totalUnrealized, $position['unrealized_pnl'] ?? '0');
        }

        return $totalUnrealized;
    }

    /**
     * Get all currency positions visible to the given user.
     *
     * Admin/Manager/Compliance can see all positions.
     * Tellers can only see positions for their currently open counter session.
     */
    public function getVisiblePositionsForUser(User $user): Collection
    {
        if ($user->role->canManageAllBranches() || $user->role->isComplianceOfficer() || $user->role->isManager()) {
            return CurrencyPosition::with('currency')->get();
        }

        $activeSession = CounterSession::where('user_id', $user->id)
            ->where('status', CounterSessionStatus::Open)
            ->first();

        if ($activeSession) {
            return $this->getAllPositions($activeSession->till_id);
        }

        return collect();
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
            'total_unrealized_pnl' => '0',
            'total_positions' => $positions->count(),
            'currencies' => [],
        ];

        foreach ($positions as $position) {
            $myrEquivalent = $this->mathService->multiply(
                $position->balance,
                $position->last_valuation_rate
            );

            $aggregates['total_balance_myr'] = $this->mathService->add(
                $aggregates['total_balance_myr'],
                $myrEquivalent
            );

            $aggregates['total_unrealized_pnl'] = $this->mathService->add(
                $aggregates['total_unrealized_pnl'],
                $position->unrealized_pnl
            );

            $aggregates['currencies'][] = [
                'currency_code' => $position->currency_code,
                'balance' => $position->balance,
                'myr_equivalent' => $myrEquivalent,
                'avg_cost_rate' => $position->avg_cost_rate,
                'unrealized_pnl' => $position->unrealized_pnl,
            ];
        }

        return $aggregates;
    }

    /**
     * Get available balance excluding pending reservations.
     *
     * @param  string  $currencyCode  Currency code
     * @param  string  $tillId  Till identifier
     * @return string Available balance as string
     */
    public function getAvailableBalance(string $currencyCode, string $tillId): string
    {
        $position = $this->getPosition($currencyCode, $tillId);
        $balance = $position ? $position->balance : '0';

        $reserved = StockReservation::where('currency_code', $currencyCode)
            ->where('till_id', $tillId)
            ->where('status', StockReservationStatus::Pending)
            ->where('expires_at', '>', now())
            ->sum('amount_foreign');

        return $this->mathService->subtract($balance, (string) $reserved);
    }

    /**
     * Reserve stock for a pending approval transaction.
     *
     * @param  Transaction  $transaction  Transaction to reserve stock for
     * @return StockReservation Created reservation
     */
    public function reserveStock(Transaction $transaction): StockReservation
    {
        return StockReservation::create([
            'transaction_id' => $transaction->id,
            'currency_code' => $transaction->currency_code,
            'till_id' => $transaction->till_id,
            'amount_foreign' => $transaction->amount_foreign,
            'status' => StockReservationStatus::Pending,
            'expires_at' => now()->addHours(24),
            'created_by' => $transaction->user_id,
        ]);
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
            ->first();

        if ($reservation) {
            $reservation->update(['status' => StockReservationStatus::Consumed]);
        }

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
            ->first();

        if ($reservation) {
            $reservation->update(['status' => StockReservationStatus::Released]);
        }

        return $reservation;
    }
}
