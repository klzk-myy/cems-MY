<?php

namespace App\Models\Compliance;

use App\Enums\RecalculationTrigger;
use App\Enums\RiskRating;
use App\Models\BaseModel;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
 * @property Carbon|null $score_changed_at
 * @property Carbon|null $next_scheduled_recalculation
 * @property RecalculationTrigger|null $recalculation_trigger
 * @property Carbon|null $locked_until
 * @property int|null $locked_by
 * @property string|null $lock_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CustomerRiskProfile extends BaseModel
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'risk_score',
        'risk_tier',
        'risk_factors',
        'previous_score',
        'score_changed_at',
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
     * Lock the risk profile for EDD review.
     *
     * Lock does not auto-expire - manual unlock only after EDD completion.
     * Uses a far-future date (100 years) to ensure isLocked() returns true
     * without relying on null which would return false.
     *
     * The write goes through a `lockForUpdate` re-read so lock/unlock
     * serialise with RiskScoringEngine's profile lock and cannot clobber a
     * concurrent lock from a stale instance.
     */
    public function lock(int $userId, string $reason): void
    {
        DB::transaction(function () use ($userId, $reason) {
            $profile = static::whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $profile->locked_until = now()->addYears(100);
            $profile->locked_by = $userId;
            $profile->lock_reason = $reason;
            $profile->save();
        });

        $this->refresh();
    }

    /**
     * Unlock the risk profile. Serialised on the profile row for the same
     * reason as lock().
     */
    public function unlock(): void
    {
        DB::transaction(function () {
            $profile = static::whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $profile->locked_until = null;
            $profile->locked_by = null;
            $profile->lock_reason = null;
            $profile->save();
        });

        $this->refresh();
    }

    /**
     * Get the risk tier for a given score.
     */
    public static function getTierForScore(int $score): string
    {
        return match (true) {
            $score >= 80 => RiskRating::Critical->value,
            $score >= 60 => RiskRating::High->value,
            $score >= 30 => RiskRating::Medium->value,
            default => RiskRating::Low->value,
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
}
