<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = [
        'currency_code',
        'rate_buy',
        'rate_sell',
        'source',
        'fetched_at',
    ];

    protected $casts = [
        'rate_buy' => 'decimal:6',
        'rate_sell' => 'decimal:6',
        'fetched_at' => 'datetime',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code');
    }

    public function scopeLatestRates($query)
    {
        return $query->orderBy('fetched_at', 'desc');
    }
}
