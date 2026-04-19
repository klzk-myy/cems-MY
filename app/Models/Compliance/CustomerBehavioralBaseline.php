<?php

namespace App\Models\Compliance;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
class CustomerBehavioralBaseline extends Model
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
     * @var array<string>
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
     * @var array<string, string>
     */
    protected $casts = [
        'currency_codes' => 'array',
        'preferred_counter_ids' => 'array',
        'last_calculated_at' => 'datetime',
    ];

    /**
     * Get the customer that owns the baseline.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
