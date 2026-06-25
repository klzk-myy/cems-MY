<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Services\System\MathService;
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

    /**
     * Get the variance (budget - actual) using high-precision MathService.
     */
    public function getVariance(): string
    {
        return app(MathService::class)->subtract(
            (string) $this->budget_amount,
            (string) $this->actual_amount
        );
    }

    /**
     * Get the variance percentage using high-precision MathService.
     */
    public function getVariancePercentage(): float
    {
        $budget = (string) $this->budget_amount;
        $math = app(MathService::class);

        if ($math->compare($budget, '0') <= 0) {
            return 0.0;
        }

        $variance = $this->getVariance();
        $ratio = $math->divide($variance, $budget, 4);
        $percentage = $math->multiply($ratio, '100');

        return (float) $percentage;
    }

    public function isOverBudget(): bool
    {
        return app(MathService::class)->compare($this->getVariance(), '0') < 0;
    }
}
