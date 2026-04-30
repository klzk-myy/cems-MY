<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
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

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeLatestRates($query)
    {
        return $query->orderBy('fetched_at', 'desc');
    }

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }
}
