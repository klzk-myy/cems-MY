<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\TellerAllocationStatus;
use App\Exceptions\Domain\AllocationValidationException;
use App\Exceptions\Domain\InsufficientAllocationBalanceException;
use App\Models\Traits\BelongsToBranch;
use App\Support\BcmathHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $user_id
 * @property int $branch_id
 * @property int|null $counter_id
 * @property string $currency_code
 * @property string $allocated_quantity
 * @property string $current_quantity
 * @property string $loaded_quantity
 * @property string $requested_quantity
 * @property string $daily_limit_myr
 * @property string $daily_used_myr
 * @property TellerAllocationStatus $status 'pending', 'approved', 'active', 'returned', 'closed', 'auto_returned', 'rejected'
 * @property Carbon $session_date
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $opened_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $rejected_at
 * @property int|null $rejected_by
 * @property string|null $rejection_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TellerAllocation extends BaseModel
{
    use BelongsToBranch, HasFactory;

    /**
     * Relations every API/UI payload of an allocation needs — shared by the
     * controller eager loads and the service result payloads so the two never
     * drift apart.
     *
     * @var list<string>
     */
    public const API_RELATIONS = ['user', 'branch', 'counter', 'approver'];

    protected $fillable = [
        'user_id',
        'branch_id',
        'counter_id',
        'currency_code',
        'allocated_quantity',
        'current_quantity',
        'loaded_quantity',
        'requested_quantity',
        'daily_limit_myr',
        'daily_used_myr',
        'status',
        'session_date',
        'approved_by',
        'approved_at',
        'opened_at',
        'closed_at',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
    ];

    protected $casts = [
        'allocated_quantity' => MoneyCast::class,
        'current_quantity' => MoneyCast::class,
        'loaded_quantity' => MoneyCast::class,
        'requested_quantity' => MoneyCast::class,
        'daily_limit_myr' => MoneyCast::class,
        'daily_used_myr' => MoneyCast::class,
        'status' => TellerAllocationStatus::class,
        'session_date' => 'date',
        'approved_at' => 'datetime',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Counter, $this>
     */
    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function isApproved(): bool
    {
        return $this->status->isApproved();
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isReturned(): bool
    {
        return $this->status->isReturned();
    }

    public function hasAvailable(float|string $quantity): bool
    {
        return BcmathHelper::compare($this->current_quantity, (string) $quantity) >= 0;
    }

    /**
     * Atomically decrement current_quantity only when sufficient funds exist.
     *
     * Uses a single conditional UPDATE (WHERE current_quantity >= amount) so
     * concurrent bookings can never drive the balance negative, closing the
     * hasAvailable()-then-deduct() race.
     *
     * When the guard fails (0 affected rows - e.g. a concurrent transaction
     * consumed the float between validation and deduction) the operation
     * aborts loudly: the exception rolls back the surrounding DB::transaction
     * instead of leaving the teller's books silently unadjusted.
     *
     * @throws InsufficientAllocationBalanceException When the balance is insufficient.
     */
    public function deduct(float|string $quantity): bool
    {
        $affected = $this->applyDecimalDelta(
            self::query()
                ->where($this->getKeyName(), $this->getKey())
                ->where('current_quantity', '>=', $quantity),
            'current_quantity',
            '-',
            $quantity
        );

        $this->refresh();

        if ($affected === 0) {
            throw new InsufficientAllocationBalanceException(
                $this->currency_code,
                (string) $this->current_quantity,
                (string) $quantity
            );
        }

        return true;
    }

    public function add(float|string $quantity): void
    {
        $this->applyDecimalDelta(
            self::query()->where($this->getKeyName(), $this->getKey()),
            'current_quantity',
            '+',
            $quantity
        );
        $this->refresh();
    }

    public function addDailyUsed(float|string $amountMyr): void
    {
        $this->applyDecimalDelta(
            self::query()->where($this->getKeyName(), $this->getKey()),
            'daily_used_myr',
            '+',
            $amountMyr
        );
        $this->refresh();
    }

    /**
     * Atomically consume daily limit capacity only when the spend fits.
     *
     * Mirrors deduct()'s conditional-UPDATE pattern: the WHERE clause is
     * evaluated inside the row lock held by the caller, so two concurrent
     * bookings can never both pass hasDailyLimitRemaining() against the same
     * stale daily_used_myr and overshoot the cap.
     *
     * @throws AllocationValidationException When the spend would exceed the daily limit.
     */
    public function addDailyUsedWithinLimit(float|string $amountMyr): void
    {
        if ($this->daily_limit_myr === null) {
            $this->addDailyUsed($amountMyr);

            return;
        }

        $affected = $this->applyDecimalDelta(
            self::query()
                ->where($this->getKeyName(), $this->getKey())
                ->whereRaw('daily_used_myr + ? <= daily_limit_myr', [$this->toNumericAmount($amountMyr)]),
            'daily_used_myr',
            '+',
            $amountMyr
        );

        $this->refresh();

        if ($affected === 0) {
            throw new AllocationValidationException(
                "Daily limit exceeded for {$this->currency_code} allocation"
            );
        }
    }

    public function subtractDailyUsed(float|string $amountMyr): void
    {
        $this->applyDecimalDelta(
            self::query()->where($this->getKeyName(), $this->getKey()),
            'daily_used_myr',
            '-',
            $amountMyr
        );
        $this->refresh();
    }

    /**
     * Decimal-precise column adjustment. increment()/decrement() are typed
     * float|int by Larastan, and passing money through float would introduce
     * IEEE-754 drift — a raw expression keeps the BCMath string end-to-end.
     * (incrementEach() itself interpolates the amount the same way.)
     *
     * @param  Builder<TellerAllocation>  $query
     */
    private function applyDecimalDelta(Builder $query, string $column, string $operator, float|string $amount): int
    {
        $amount = $this->toNumericAmount($amount);

        return $query->update([$column => DB::raw("{$column} {$operator} {$amount}")]);
    }

    /**
     * Normalize a validated numeric amount to a decimal string for the query
     * builder's increment/decrement. A string keeps the arithmetic in the
     * decimal column domain — casting to float would introduce IEEE-754 drift
     * on money quantities (the project's BCMath-only rule).
     */
    private function toNumericAmount(float|int|string $quantity): string
    {
        if (is_int($quantity)) {
            return (string) $quantity;
        }

        if (is_float($quantity)) {
            // %.10F avoids scientific notation for very small/large floats.
            $quantity = sprintf('%.10F', $quantity);
        }

        if (! is_numeric($quantity)) {
            throw new \InvalidArgumentException('Allocation amount must be numeric.');
        }

        return BcmathHelper::add($quantity, '0');
    }

    public function hasDailyLimitRemaining(float|string $amountMyr): bool
    {
        if ($this->daily_limit_myr === null) {
            return true;
        }
        $remaining = BcmathHelper::subtract((string) $this->daily_limit_myr, (string) $this->daily_used_myr);

        return BcmathHelper::compare($remaining, (string) $amountMyr) >= 0;
    }

    public function approve(User $approver, float|string $allocatedQuantity, float|string|null $dailyLimitMyr = null): void
    {
        $data = [
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'allocated_quantity' => $allocatedQuantity,
            'current_quantity' => $allocatedQuantity,
            'status' => TellerAllocationStatus::Approved,
        ];

        if ($dailyLimitMyr !== null) {
            $data['daily_limit_myr'] = $dailyLimitMyr;
        }

        $this->update($data);
    }

    public function activate(): void
    {
        $this->update([
            'status' => TellerAllocationStatus::Active,
            'opened_at' => now(),
        ]);
    }

    public function returnToPool(): void
    {
        $this->update([
            'status' => TellerAllocationStatus::Returned,
            'closed_at' => now(),
        ]);
    }

    public function reject(User $rejector, ?string $reason = null): void
    {
        $this->update([
            'status' => TellerAllocationStatus::Rejected,
            'rejected_by' => $rejector->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Terminate a pending/approved request without a rejection verdict —
     * e.g. branch settlement sweeping stale requests at day close. Reuses
     * the rejected_* columns to record who ended the request and why.
     */
    public function cancel(User $actor, ?string $reason = null): void
    {
        $this->update([
            'status' => TellerAllocationStatus::Cancelled,
            'rejected_by' => $actor->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }
}
