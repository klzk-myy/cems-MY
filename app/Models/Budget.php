<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends BaseModel
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
        'budget_amount' => MoneyCast::class,
        'actual_amount' => MoneyCast::class,
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_code', 'account_code');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_code', 'period_code');
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
