<?php

namespace App\Models\Compliance;

use App\Enums\RecalculationTrigger;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Customer Risk Profile Model
 *
 * Stores customer risk scoring, tier classification, and risk factor data.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $risk_score
 * @property string $risk_tier
 * @property array|null $risk_factors
 * @property int|null $previous_score
 * @property \Illuminate\Support\Carbon|null $score_changed_at
 * @property \Illuminate\Support\Carbon|null $next_scheduled_recalculation
 * @property RecalculationTrigger|null $recalculation_trigger
 * @property \Illuminate\Support\Carbon|null $locked_until
 * @property int|null $locked_by
 * @property string|null $lock_reason
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class CustomerRiskProfile extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'customer_id',
        'risk_score',
        'risk_tier',
        'risk_factors',
        'previous_score',
        'score_changed_at',
        'next_scheduled_recalculation',
        'recalculation_trigger',
        'locked_until',
        'locked_by',
        'lock_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'risk_factors' => 'array',
        'score_changed_at' => 'datetime',
        'next_scheduled_recalculation' => 'datetime',
        'locked_until' => 'datetime',
        'recalculation_trigger' => RecalculationTrigger::class,
    ];

    /**
     * Get the customer that owns the risk profile.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user who locked the profile.
     */
    public function lockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * Check if the profile is currently locked.
     */
    public function isLocked(): bool
    {
        if ($this->locked_until === null) {
            return false;
        }

        return $this->locked_until->isFuture();
    }

    /**
     * Lock the risk profile.
     */
    public function lock(int $userId, string $reason): void
    {
        $this->locked_until = now()->addHour();
        $this->locked_by = $userId;
        $this->lock_reason = $reason;
        $this->save();
    }

    /**
     * Unlock the risk profile.
     */
    public function unlock(): void
    {
        $this->locked_until = null;
        $this->locked_by = null;
        $this->lock_reason = null;
        $this->save();
    }

    /**
     * Recalculate the risk score with a new value.
     */
    public function recalculateWithScore(int $score): self
    {
        $this->previous_score = $this->risk_score;
        $this->risk_score = $score;
        $this->risk_tier = self::getTierForScore($score);
        $this->score_changed_at = now();
        $this->save();

        return $this;
    }

    /**
     * Get the risk tier for a given score.
     */
    public static function getTierForScore(int $score): string
    {
        return match (true) {
            $score <= 25 => 'Low',
            $score <= 50 => 'Medium',
            $score <= 75 => 'High',
            default => 'Critical',
        };
    }

    /**
     * Create a risk profile for a customer with a given score.
     */
    public static function createForCustomer(int $customerId, int $score): self
    {
        return self::create([
            'customer_id' => $customerId,
            'risk_score' => $score,
            'risk_tier' => self::getTierForScore($score),
        ]);
    }

    /**
     * Create a risk profile from risk factors.
     */
    public static function createFromFactors(int $customerId, array $factors): self
    {
        $totalContribution = 0;

        if (isset($factors['contributions']) && is_array($factors['contributions'])) {
            foreach ($factors['contributions'] as $contribution) {
                if (isset($contribution['value'])) {
                    $totalContribution += (int) $contribution['value'];
                }
            }
        }

        $baseScore = 20;
        $riskScore = min($baseScore + $totalContribution, 100);

        return self::create([
            'customer_id' => $customerId,
            'risk_score' => $riskScore,
            'risk_tier' => self::getTierForScore($riskScore),
            'risk_factors' => $factors,
        ]);
    }
}
