<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRateHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'currency_code',
        'rate',
        'effective_date',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'rate' => 'decimal:6',
        'effective_date' => 'date',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for specific currency
     */
    public function scopeForCurrency($query, string $code)
    {
        return $query->where('currency_code', $code);
    }

    /**
     * Scope for date range
     */
    public function scopeForDateRange($query, string $from, string $to)
    {
        return $query->whereBetween('effective_date', [$from, $to]);
    }

    /**
     * Get latest rate for a currency
     */
    public static function getLatestRate(string $currencyCode): ?float
    {
        $latest = self::forCurrency($currencyCode)
            ->orderBy('effective_date', 'desc')
            ->first();

        return $latest ? (float) $latest->rate : null;
    }
}
