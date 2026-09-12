<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $branch_id
 * @property string $source
 */
class ExchangeRate extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'currency_code',
        'rate_buy',
        'rate_sell',
        'source',
        'fetched_at',
        'effective_date',
    ];

    protected $casts = [
        'rate_buy' => MoneyCast::class.':6',
        'rate_sell' => MoneyCast::class.':6',
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
