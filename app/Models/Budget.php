<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_code',
        'period_code',
        'budget_amount',
        'actual_amount',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'budget_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_code', 'account_code');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getVariance(): float
    {
        return (float) $this->budget_amount - (float) $this->actual_amount;
    }

    public function getVariancePercentage(): float
    {
        if ((float) $this->budget_amount == 0) {
            return 0;
        }

        return ($this->getVariance() / (float) $this->budget_amount) * 100;
    }

    public function isOverBudget(): bool
    {
        return $this->getVariance() < 0;
    }
}
