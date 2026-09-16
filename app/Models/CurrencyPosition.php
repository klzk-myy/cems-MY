<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\StockReservationStatus;
use App\Support\BcmathHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $currency_code
 * @property string $branch_id Branch code string (legacy numeric ids were converted to codes)
 * @property numeric-string $quantity
 * @property numeric-string $average_cost
 * @property numeric-string $total_cost
 * @property numeric-string $current_rate
 * @property numeric-string $current_value
 * @property numeric-string $unrealized_gain_loss
 * @property Carbon|null $last_revalued_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $balance Alias of quantity
 * @property-read string $avg_cost_rate
 * @property-read string $avg_cost
 * @property-read string $last_valuation_rate
 * @property-read string $unrealized_pnl
 * @property-read string|null $last_valuation_at
 * @property-read string $market_value
 * @property-read string $unrealized_pl
 * @property-read string $previous_rate
 * @property-read string $held Quantity locked by pending stock reservations
 * @property-read string $available quantity minus held
 * @property-read string $total Alias of quantity
 * @property-read string $pnl Alias of unrealized_gain_loss
 */
class CurrencyPosition extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'currency_code',
        'branch_id',
        'quantity',
        'average_cost',
        'total_cost',
        'current_rate',
        'current_value',
        'unrealized_gain_loss',
        'last_revalued_at',
    ];

    protected $casts = [
        'quantity' => MoneyCast::class,
        'average_cost' => MoneyCast::class.':8',
        'total_cost' => MoneyCast::class,
        'current_rate' => MoneyCast::class.':8',
        'current_value' => MoneyCast::class,
        'unrealized_gain_loss' => MoneyCast::class,
        'last_revalued_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code');
    }

    /**
     * Get the branch associated with this currency position.
     *
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Alias for quantity — used by views expecting "balance".
     */
    public function getBalanceAttribute(): string
    {
        return $this->quantity;
    }

    /**
     * Alias for average_cost — used by views expecting "avg_cost_rate".
     */
    public function getAvgCostRateAttribute(): string
    {
        return $this->average_cost ?? '0';
    }

    /**
     * Alias for average_cost — used by views expecting "avg_cost".
     */
    public function getAvgCostAttribute(): string
    {
        return $this->average_cost ?? '0';
    }

    /**
     * Alias for current_rate — used by views expecting "last_valuation_rate".
     */
    public function getLastValuationRateAttribute(): string
    {
        return $this->current_rate ?? '0';
    }

    /**
     * Alias for unrealized_gain_loss — used by views expecting "unrealized_pnl".
     */
    public function getUnrealizedPnlAttribute(): string
    {
        return $this->unrealized_gain_loss;
    }

    /**
     * Alias for last_revalued_at — used by views expecting "last_valuation_at".
     */
    public function getLastValuationAtAttribute(): ?string
    {
        return $this->last_revalued_at;
    }

    /**
     * Computed market value in MYR: quantity × current_rate.
     * Falls back to average_cost if current_rate is null (never been revalued).
     */
    public function getMarketValueAttribute(): string
    {
        $rate = $this->current_rate;

        if (! $rate || BcmathHelper::compare($rate, '0') === 0) {
            $rate = $this->average_cost;
        }

        if (! $rate || BcmathHelper::compare($rate, '0') === 0) {
            return '0';
        }

        return BcmathHelper::multiply($this->quantity, $rate);
    }

    /**
     * Alias for unrealized_gain_loss — used by views expecting "unrealized_pl".
     */
    public function getUnrealizedPlAttribute(): string
    {
        return $this->unrealized_gain_loss;
    }

    /**
     * Previous rate for revaluation view — returns average_cost as baseline.
     */
    public function getPreviousRateAttribute(): string
    {
        return $this->average_cost ?? '0';
    }

    /**
     * Stock held by pending reservations at this branch. Reservations key on
     * the till (counter code), so the lookup resolves through the branch's
     * counters.
     */
    public function getHeldAttribute(): string
    {
        $counterCodes = Counter::query()
            ->where('branch_id', $this->branch_id)
            ->pluck('code');

        if ($counterCodes->isEmpty()) {
            return '0';
        }

        return (string) StockReservation::query()
            ->where('currency_code', $this->currency_code)
            ->where('status', StockReservationStatus::Pending->value)
            ->whereIn('till_id', $counterCodes)
            ->sum('amount_foreign');
    }

    /**
     * Quantity not locked by pending reservations.
     */
    public function getAvailableAttribute(): string
    {
        $available = BcmathHelper::subtract((string) $this->quantity, $this->held);

        return BcmathHelper::compare($available, '0') < 0 ? '0' : $available;
    }

    /**
     * Total on-hand quantity — alias of quantity for views.
     */
    public function getTotalAttribute(): string
    {
        return (string) $this->quantity;
    }

    /**
     * Unrealized P&L alias for views.
     */
    public function getPnlAttribute(): string
    {
        return (string) ($this->unrealized_gain_loss ?? '0');
    }

    // Legacy mutators

    public function setBalanceAttribute($value): void
    {
        $this->attributes['quantity'] = $value;
    }

    public function setAvgCostRateAttribute($value): void
    {
        $this->attributes['average_cost'] = $value;
    }

    public function setLastValuationRateAttribute($value): void
    {
        $this->attributes['current_rate'] = $value;
    }

    public function setUnrealizedPnlAttribute($value): void
    {
        $this->attributes['unrealized_gain_loss'] = $value;
    }

    public function setLastValuationAtAttribute($value): void
    {
        $this->attributes['last_revalued_at'] = $value;
    }

    public function setTillIdAttribute($value): void
    {
        // Only set branch_id if not already set explicitly
        if (! isset($this->attributes['branch_id'])) {
            $this->attributes['branch_id'] = $value;
        }
    }
}
