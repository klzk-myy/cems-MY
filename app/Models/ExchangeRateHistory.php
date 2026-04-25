<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRateHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
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

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeForCurrency($query, string $code)
    {
        return $query->where('currency_code', $code);
    }

    public function scopeForDateRange($query, string $from, string $to)
    {
        return $query->whereBetween('effective_date', [$from, $to]);
    }

    public static function getLatestRate(string $currencyCode, ?int $branchId = null): ?float
    {
        $query = self::forCurrency($currencyCode);
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }
        $latest = $query->orderBy('effective_date', 'desc')->first();

        return $latest ? (float) $latest->rate : null;
    }
}
