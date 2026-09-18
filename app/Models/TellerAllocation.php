<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\TellerAllocationStatus;
use App\Exceptions\Domain\InsufficientAllocationBalanceException;
use App\Models\Traits\BelongsToBranch;
use App\Support\BcmathHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $branch_id
 * @property int|null $counter_id
 * @property string $currency_code
 * @property string $allocated_amount
 * @property string $current_balance
 * @property string $loaded_balance
 * @property string $requested_amount
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
        'allocated_amount',
        'current_balance',
        'loaded_balance',
        'requested_amount',
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
        'allocated_amount' => MoneyCast::class,
        'current_balance' => MoneyCast::class,
        'loaded_balance' => MoneyCast::class,
        'requested_amount' => MoneyCast::class,
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

    public function hasAvailable(float|string $amount): bool
    {
        return BcmathHelper::compare($this->current_balance, (string) $amount) >= 0;
    }

    /**
     * Atomically decrement current_balance only when sufficient funds exist.
     *
     * Uses a single conditional UPDATE (WHERE current_balance >= amount) so
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
    public function deduct(float|string $amount): bool
    {
        $affected = static::query()
            ->where($this->getKeyName(), $this->getKey())
            ->where('current_balance', '>=', $amount)
            ->decrement('current_balance', $this->toNumericAmount($amount));

        $this->refresh();

        if ($affected === 0) {
            throw new InsufficientAllocationBalanceException(
                $this->currency_code,
                (string) $this->current_balance,
                (string) $amount
            );
        }

        return true;
    }

    public function add(float|string $amount): void
    {
        $this->increment('current_balance', $this->toNumericAmount($amount));
        $this->refresh();
    }

    public function addDailyUsed(float|string $amountMyr): void
    {
        $this->increment('daily_used_myr', $this->toNumericAmount($amountMyr));
        $this->refresh();
    }

    public function subtractDailyUsed(float|string $amountMyr): void
    {
        $this->decrement('daily_used_myr', $this->toNumericAmount($amountMyr));
        $this->refresh();
    }

    /**
     * Normalize a validated numeric amount into the float|int shape the
     * query builder's increment/decrement expects.
     */
    private function toNumericAmount(float|int|string $amount): float|int
    {
        if (is_int($amount) || is_float($amount)) {
            return $amount;
        }

        if (is_numeric($amount)) {
            return (float) $amount;
        }

        throw new \InvalidArgumentException('Allocation amount must be numeric.');
    }

    public function hasDailyLimitRemaining(float|string $amountMyr): bool
    {
        if ($this->daily_limit_myr === null) {
            return true;
        }
        $remaining = BcmathHelper::subtract((string) $this->daily_limit_myr, (string) $this->daily_used_myr);

        return BcmathHelper::compare($remaining, (string) $amountMyr) >= 0;
    }

    public function approve(User $approver, float|string $allocatedAmount, float|string|null $dailyLimitMyr = null): void
    {
        $data = [
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'allocated_amount' => $allocatedAmount,
            'current_balance' => $allocatedAmount,
            'status' => TellerAllocationStatus::APPROVED,
        ];

        if ($dailyLimitMyr !== null) {
            $data['daily_limit_myr'] = $dailyLimitMyr;
        }

        $this->update($data);
    }

    public function activate(): void
    {
        $this->update([
            'status' => TellerAllocationStatus::ACTIVE,
            'opened_at' => now(),
        ]);
    }

    public function returnToPool(): void
    {
        $this->update([
            'status' => TellerAllocationStatus::RETURNED,
            'closed_at' => now(),
        ]);
    }

    public function reject(User $rejector, ?string $reason = null): void
    {
        $this->update([
            'status' => TellerAllocationStatus::REJECTED,
            'rejected_by' => $rejector->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }
}
