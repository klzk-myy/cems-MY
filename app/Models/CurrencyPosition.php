<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyPosition extends Model
{
    use HasFactory;

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
        'balance' => 'decimal:4',
        'avg_cost_rate' => 'decimal:6',
        'last_valuation_rate' => 'decimal:6',
        'unrealized_pnl' => 'decimal:4',
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
}
