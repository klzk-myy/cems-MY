<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Services\System\MathService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyPosition extends BaseModel
{
    use HasFactory;

    protected MathService $mathService;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->mathService = app(MathService::class);
    }

    protected $fillable = [
        'currency_code',
        'branch_id',
        'till_id',
        'balance',
        'avg_cost_rate',
        'last_valuation_rate',
        'unrealized_pnl',
        'last_valuation_at',
    ];

    protected $casts = [
        'balance' => MoneyCast::class,
        'avg_cost_rate' => MoneyCast::class.':6',
        'last_valuation_rate' => MoneyCast::class.':6',
        'unrealized_pnl' => MoneyCast::class,
        'last_valuation_at' => 'datetime',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code');
    }

    /**
     * Get the branch associated with this currency position.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class, 'till_id');
    }

    /**
     * Alias for balance — used by views expecting "quantity".
     */
    public function getQuantityAttribute(): string
    {
        return $this->balance;
    }

    /**
     * Alias for avg_cost_rate — used by views expecting "avg_cost".
     */
    public function getAvgCostAttribute(): string
    {
        return $this->avg_cost_rate ?? '0';
    }

    /**
     * Alias for avg_cost_rate — used by views expecting "average_cost".
     */
    public function getAverageCostAttribute(): string
    {
        return $this->avg_cost_rate ?? '0';
    }

    /**
     * Computed market value in MYR: balance × last_valuation_rate.
     * Falls back to avg_cost_rate if last_valuation_rate is null (never been revalued).
     */
    public function getMarketValueAttribute(): string
    {
        $rate = $this->last_valuation_rate;

        if (! $rate || $this->mathService->compare($rate, '0') === 0) {
            $rate = $this->avg_cost_rate;
        }

        if (! $rate || $this->mathService->compare($rate, '0') === 0) {
            return '0';
        }

        return $this->mathService->multiply($this->balance, $rate);
    }

    /**
     * Alias for unrealized_pnl — used by views expecting "unrealized_pl".
     */
    public function getUnrealizedPlAttribute(): string
    {
        return $this->unrealized_pnl;
    }

    /**
     * Alias for last_valuation_rate — used by views expecting "current_rate".
     */
    public function getCurrentRateAttribute(): string
    {
        return $this->last_valuation_rate ?? '0';
    }

    /**
     * Previous rate for revaluation view — returns avg_cost_rate as baseline.
     */
    public function getPreviousRateAttribute(): string
    {
        return $this->avg_cost_rate ?? '0';
    }

    /**
     * Whether this position needs revaluation (always false — revaluation
     * status is per-month, not per-position; computed at service level).
     */
    public function getNeedsRevaluationAttribute(): bool
    {
        return false;
    }
}
