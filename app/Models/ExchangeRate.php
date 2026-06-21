<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected $casts = [
        'rate_buy' => 'decimal:4',
        'rate_sell' => 'decimal:4',
        'fetched_at' => 'datetime',
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
}
