<?php

namespace App\Services\Accounting;

use App\Enums\CounterSessionStatus;
use App\Enums\StockReservationStatus;
use App\Enums\TransactionType;
use App\Exceptions\Domain\AccountingPeriodException;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\StockReservation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Contracts\CurrencyPositionServiceInterface;
use App\Services\System\CacheInvalidationService;
use App\Services\System\MathService;
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

    /**
     * Precision for position calculations (4 decimals for rates/balances)
     */
    protected int $positionPrecision = 4;

    /**
     * Create a new CurrencyPositionService instance.
     *
     * @param  MathService  $mathService  Math service for high-precision calculations
     * @param  CurrencyPositionLockService  $lockService  Lock service for pessimistic position locking
     */
    public function __construct(
        MathService $mathService,
        CurrencyPositionLockService $lockService,
        CacheInvalidationService $cacheInvalidationService
    ) {
        $this->mathService = $mathService;
        $this->lockService = $lockService;
        $this->cacheInvalidationService = $cacheInvalidationService;
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
            if ($type === TransactionType::Buy->value) {
                // Buying foreign currency - lock or create the position
                $position = $this->lockService->lock($branchId, $currencyCode);

                $oldBalance = $position->quantity;
                $oldAvgCost = $position->average_cost;

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

                $position = $this->lockService->adjust($position, $amount, 'add');
            } else {
                // Selling foreign currency - only lock an existing position; do not
                // create a zero-quantity row when no position exists.
                $position = $this->lockService->findForUpdate($branchId, $currencyCode);

                if ($position === null || $this->mathService->compare($position->quantity, '0') <= 0) {
                    throw new AccountingPeriodException(
                        'Cannot sell: Position is empty or negative'
                    );
                }

                if ($this->mathService->compare($position->quantity, $amount) < 0) {
                    throw new AccountingPeriodException(
                        "Insufficient balance. Available: {$position->quantity}, Requested: {$amount}"
                    );
                }

                $oldAvgCost = $position->average_cost;
                $newAvgCost = $oldAvgCost; // Cost basis doesn't change on sale

                $position = $this->lockService->adjust($position, $amount, 'subtract');
            }

            $newBalance = $position->quantity;

            $roundedAvgCost = $this->mathService->round($newAvgCost, $this->positionPrecision);
            $roundedRate = $this->mathService->round($rate, $this->positionPrecision);

            $position->update([
                'average_cost' => $roundedAvgCost,
                'current_rate' => $roundedRate,
                'unrealized_gain_loss' => $this->mathService->round(
                    $this->mathService->calculateRevaluationPnl($newBalance, $roundedAvgCost, $roundedRate),
                    $this->positionPrecision
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
     * Reverse the position impact of a transaction (release/cancellation).
     *
     * Sign convention mirrors updatePosition: a Buy added amount_foreign to
     * the position, so reversing it subtracts the same amount; a Sell removed
     * it, so reversing it adds it back. The average cost basis is intentionally
     * left untouched, matching updatePosition's behaviour on sells.
     */
    public function reversePositions(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $position = $this->lockService->findForUpdate(
                (string) $transaction->branch_id,
                $transaction->currency_code
            );

            if ($position === null) {
                return;
            }

            $direction = $transaction->type === TransactionType::Buy ? 'subtract' : 'add';
            $position = $this->lockService->adjust($position, (string) $transaction->amount_foreign, $direction);

            $newBalance = $position->quantity;
            $roundedAvgCost = $this->mathService->round($position->average_cost, $this->positionPrecision);
            $roundedRate = $this->mathService->round($position->current_rate ?? $position->average_cost, $this->positionPrecision);

            $position->update([
                'unrealized_gain_loss' => $this->mathService->round(
                    $this->mathService->calculateRevaluationPnl($newBalance, $roundedAvgCost, $roundedRate),
                    $this->positionPrecision
                ),
                'last_revalued_at' => now(),
            ]);
        });

        // Invalidate cache for available balance
        $this->cacheInvalidationService->forgetPosition(
            (string) $transaction->branch_id,
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
            throw new AccountingPeriodException(
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
            throw new AccountingPeriodException(
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
            // Till identifiers are counter codes (see convert_numeric_till_ids migration);
            // counter_sessions has no till_id column, resolve it through the counter.
            $activeSession->loadMissing('counter');

            return $this->getAllPositions((string) $activeSession->counter?->code);
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
     * @param  string  $currencyCode  Currency code
     * @param  string  $locationId  Branch identifier (used for position and reservation lookup)
     * @return string Available balance as string
     */
    public function getAvailableBalance(string $currencyCode, string $locationId): string
    {
        return DB::transaction(function () use ($currencyCode, $locationId) {
            $position = $this->lockService->findForUpdate($locationId, $currencyCode);
            $quantity = $position ? $position->quantity : '0';

            $reserved = StockReservation::where('currency_code', $currencyCode)
                ->where('till_id', $locationId)
                ->where('status', StockReservationStatus::Pending)
                ->where('expires_at', '>', now())
                ->lockForUpdate()
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
        if (empty($transaction->currency_code) || strlen($transaction->currency_code) !== 3) {
            throw new \InvalidArgumentException('Invalid currency code for stock reservation');
        }

        if (! $transaction->amount_foreign || $this->mathService->compare((string) $transaction->amount_foreign, '0') <= 0) {
            throw new \InvalidArgumentException('Amount foreign must be positive for stock reservation');
        }

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
