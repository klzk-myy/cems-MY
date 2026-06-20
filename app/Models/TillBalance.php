<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Traits\BelongsToBranch;
use App\Services\System\MathService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TillBalance extends BaseModel
{
    use BelongsToBranch, HasFactory;

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
        'buy_total_foreign',
        'sell_total_foreign',
    ];

    protected $casts = [
        'opening_balance' => MoneyCast::class,
        'closing_balance' => MoneyCast::class,
        'variance' => MoneyCast::class,
        'foreign_total' => MoneyCast::class,
        'transaction_total' => MoneyCast::class,
        'buy_total_foreign' => MoneyCast::class,
        'sell_total_foreign' => MoneyCast::class,
        'date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class, 'till_id');
    }

    /**
     * Calculate the expected balance (opening + transaction activity)
     * For foreign currency: expected = opening_balance + buy_total_foreign - sell_total_foreign
     * This correctly tracks position for both buys (adds to position) and sells (reduces position)
     */
    public function getExpectedBalance(): string
    {
        $mathService = app(MathService::class);
        $opening = (string) $this->opening_balance;
        $buyTotal = $this->buy_total_foreign !== null ? (string) $this->buy_total_foreign : '0';
        $sellTotal = $this->sell_total_foreign !== null ? (string) $this->sell_total_foreign : '0';

        // net foreign = buys - sells (buys increase position, sells decrease position)
        $netForeign = $mathService->subtract($buyTotal, $sellTotal);

        return $mathService->add($opening, $netForeign);
    }

    /**
     * Calculate variance between closing balance and expected balance
     * Expected = opening_balance + foreign_total (transaction activity)
     */
    public function calculateVariance(): string
    {
        if ($this->closing_balance === null) {
            return '0';
        }

        $mathService = app(MathService::class);
        $closing = (string) $this->closing_balance;
        $expected = $this->getExpectedBalance();

        return $mathService->subtract($closing, $expected);
    }

    /**
     * Check if variance exceeds threshold
     */
    public function hasSignificantVariance(string $threshold = '100.00'): bool
    {
        $mathService = app(MathService::class);
        $variance = $this->calculateVariance();
        $absVariance = $mathService->abs($variance);

        return $mathService->compare($absVariance, $threshold) > 0;
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
