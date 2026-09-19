<?php

namespace App\Models\Compliance;

use App\Casts\MoneyCast;
use App\Models\BaseModel;
use App\Models\Customer;
use App\Support\BcmathHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Customer Behavioral Baseline Model
 *
 * Stores customer behavioral patterns for anomaly detection.
 *
 * @property int $id
 * @property int $customer_id
 * @property array|null $currency_codes
 * @property float $avg_transaction_size_myr
 * @property float $avg_transaction_frequency
 * @property array|null $preferred_counter_ids
 * @property string|null $registered_location
 * @property Carbon|null $last_calculated_at
 * @property int $baseline_version
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CustomerBehavioralBaseline extends BaseModel
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'customer_behavioral_baselines';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'currency_codes',
        'avg_transaction_size_myr',
        'avg_transaction_frequency',
        'preferred_counter_ids',
        'registered_location',
        'last_calculated_at',
        'baseline_version',
    ];

    /**
     * The attributes that should be cast.
     *
     * Money columns use MoneyCast (exact decimal strings) so BCMath math in
     * deviationPercent()/detectDeviation() never sees float-rounded values.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'currency_codes' => 'array',
        'preferred_counter_ids' => 'array',
        'last_calculated_at' => 'datetime',
        'avg_transaction_size_myr' => MoneyCast::class,
        'avg_transaction_frequency' => 'decimal:2',
    ];

    /**
     * Get the customer that owns the baseline.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function detectDeviation(float|string $currentAmount, float $thresholdPercent = 50.0): bool
    {
        if ((float) $this->avg_transaction_size_myr <= 0) {
            return false;
        }

        return $this->deviationPercent($currentAmount) > $thresholdPercent;
    }

    public function calculateDeviationScore(float|string $currentAmount): float
    {
        if ((float) $this->avg_transaction_size_myr <= 0) {
            return 0.0;
        }

        return round($this->deviationPercent($currentAmount), 2);
    }

    /**
     * Deviation of an amount from the baseline average, as a percentage,
     * computed with BCMath to keep money comparisons exact.
     */
    protected function deviationPercent(float|string $currentAmount): float
    {
        $helper = BcmathHelper::class;
        $baseline = (string) $this->avg_transaction_size_myr;
        $amountMyr = (string) $currentAmount;

        $diff = $helper::abs($helper::subtract($amountMyr, $baseline));

        return (float) $helper::multiply($helper::divide($diff, $baseline), '100');
    }

    public function isCurrencyUnusual(string $currencyCode): bool
    {
        return ! in_array($currencyCode, $this->currency_codes ?? [], true);
    }

    public function isCounterUnusual(string $counterId): bool
    {
        return ! in_array($counterId, $this->preferred_counter_ids ?? [], true);
    }

    public function isStale(?int $maxDays = 90): bool
    {
        if ($this->last_calculated_at === null) {
            return true;
        }

        return $this->last_calculated_at->diffInDays(now()) > ($maxDays ?? 90);
    }
}
