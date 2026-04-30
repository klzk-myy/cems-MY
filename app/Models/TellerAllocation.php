<?php

namespace App\Models;

use App\Enums\TellerAllocationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TellerAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'branch_id',
        'counter_id',
        'currency_code',
        'allocated_amount',
        'current_balance',
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
        'allocated_amount' => 'decimal:4',
        'current_balance' => 'decimal:4',
        'requested_amount' => 'decimal:4',
        'daily_limit_myr' => 'decimal:4',
        'daily_used_myr' => 'decimal:4',
        'status' => TellerAllocationStatus::class,
        'session_date' => 'date',
        'approved_at' => 'datetime',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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
        return bccomp($this->current_balance, (string) $amount, 4) >= 0;
    }

    public function deduct(float|string $amount): void
    {
        $this->decrement('current_balance', $amount);
    }

    public function add(float|string $amount): void
    {
        $this->increment('current_balance', $amount);
    }

    public function addDailyUsed(float|string $amountMyr): void
    {
        $this->increment('daily_used_myr', $amountMyr);
    }

    public function hasDailyLimitRemaining(float|string $amountMyr): bool
    {
        if ($this->daily_limit_myr === null) {
            return true;
        }
        $remaining = bcsub((string) $this->daily_limit_myr, (string) $this->daily_used_myr, 4);

        return bccomp($remaining, (string) $amountMyr, 4) >= 0;
    }

    public function approve(User $approver, float|string $allocatedAmount, float|string|null $dailyLimitMyr = null): void
    {
        $this->update([
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'allocated_amount' => $allocatedAmount,
            'current_balance' => $allocatedAmount,
            'daily_limit_myr' => $dailyLimitMyr ?? '0',
            'status' => TellerAllocationStatus::APPROVED,
        ]);
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

    public function forceReturn(): void
    {
        $this->update([
            'status' => TellerAllocationStatus::AUTO_RETURNED,
            'closed_at' => now(),
        ]);
    }
}
