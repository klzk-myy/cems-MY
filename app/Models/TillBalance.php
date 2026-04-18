<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TillBalance extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'till_id',
        'currency_code',
        'branch_id',
        'opening_balance',
        'closing_balance',
        'variance',
        'date',
        'opened_by',
        'closed_by',
        'closed_at',
        'notes',
        'foreign_total',
        'transaction_total',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:4',
        'closing_balance' => 'decimal:4',
        'variance' => 'decimal:4',
        'date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code');
    }

    /**
     * Get the branch associated with this till balance.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function opener()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Calculate the expected balance (opening + transaction activity)
     */
    public function getExpectedBalance(): float
    {
        $opening = (float) $this->opening_balance;
        $foreignTotal = $this->foreign_total !== null ? (float) $this->foreign_total : 0.0;

        return $opening + $foreignTotal;
    }

    /**
     * Calculate variance between closing balance and expected balance
     * Expected = opening_balance + foreign_total (transaction activity)
     */
    public function calculateVariance(): float
    {
        if ($this->closing_balance === null) {
            return 0.0;
        }

        return (float) $this->closing_balance - $this->getExpectedBalance();
    }

    /**
     * Check if variance exceeds threshold
     */
    public function hasSignificantVariance(float $threshold = 100.00): bool
    {
        return abs($this->calculateVariance()) > $threshold;
    }

    /**
     * Scope for today's balances
     */
    public function scopeToday($query)
    {
        return $query->whereDate('date', today());
    }

    /**
     * Scope for open tills (not yet closed)
     */
    public function scopeOpen($query)
    {
        return $query->whereNull('closed_at');
    }

    /**
     * Scope for closed tills
     */
    public function scopeClosed($query)
    {
        return $query->whereNotNull('closed_at');
    }
}
