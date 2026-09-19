<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\BcmathHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $account_code
 * @property string $period_code
 * @property string $budget_myr
 * @property string $actual_myr
 * @property string|null $notes
 * @property int $created_by
 * @property-read ChartOfAccount $account
 * @property-read User $creator
 * @property-read AccountingPeriod|null $period
 */
class Budget extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'account_code',
        'period_code',
        'budget_myr',
        'actual_myr',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'budget_myr' => MoneyCast::class,
        'actual_myr' => MoneyCast::class,
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
     * Get the variance (budget - actual) using high-precision BcmathHelper.
     */
    public function getVariance(): string
    {
        return BcmathHelper::subtract(
            (string) $this->budget_myr,
            (string) $this->actual_myr
        );
    }

    /**
     * Get the variance percentage using high-precision BcmathHelper.
     */
    public function getVariancePercentage(): float
    {
        $budget = (string) $this->budget_myr;

        if (BcmathHelper::compare($budget, '0') <= 0) {
            return 0.0;
        }

        $variance = $this->getVariance();
        $ratio = BcmathHelper::divide($variance, $budget, 4);
        $percentage = BcmathHelper::multiply($ratio, '100');

        return (float) $percentage;
    }

    public function isOverBudget(): bool
    {
        return BcmathHelper::compare($this->getVariance(), '0') < 0;
    }
}
