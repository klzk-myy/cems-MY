<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\ValueObjects\QuoteConvention;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $branch_id
 * @property string $currency_code
 * @property numeric-string $rate_buy
 * @property numeric-string $rate_sell
 * @property string|null $spread_applied
 * @property int $rate_unit
 * @property bool $rate_inverse
 * @property string $source
 * @property Carbon|null $fetched_at
 * @property Carbon|null $effective_date
 */
class ExchangeRate extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'currency_code',
        'rate_buy',
        'rate_sell',
        'rate_unit',
        'rate_inverse',
        'source',
        'spread_applied',
        'fetched_at',
        'effective_date',
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'rate_buy' => MoneyCast::class.':8',
        'rate_sell' => MoneyCast::class.':8',
        'rate_unit' => 'integer',
        'rate_inverse' => 'boolean',
        'fetched_at' => 'datetime',
        'effective_date' => 'datetime',
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeLatestRates(Builder $query): Builder
    {
        return $query->orderBy('fetched_at', 'desc');
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * The quote convention this row snapshots (rate_unit + rate_inverse).
     */
    public function quoteConvention(): QuoteConvention
    {
        return QuoteConvention::for($this);
    }

    /**
     * Normalize one of this row's quoted rate values to per-unit MYR
     * (8 decimals, matching the transactions.rate convention).
     *
     * @param  string  $quoted  rate in this row's quote convention; must be numeric
     * @return numeric-string
     */
    public function perUnitRate(string $quoted): string
    {
        return $this->quoteConvention()->toPerUnit($quoted);
    }

    /**
     * Restrict to rows whose scheduled effective_date has arrived
     * (NULL effective_date means the row is active immediately).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('effective_date')
            ->orWhere('effective_date', '<=', now()));
    }
}
