<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $counter_session_id
 * @property int $from_user_id
 * @property int $to_user_id
 * @property int $supervisor_id
 * @property Carbon $handover_time
 * @property bool $physical_count_verified
 * @property string $variance_myr
 * @property string|null $variance_notes
 * @property bool $yellow_variance
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CounterSession $counterSession
 * @property-read User $fromUser
 * @property-read User $toUser
 * @property-read User $supervisor
 */
class CounterHandover extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'from_user_id',
        'to_user_id',
        'supervisor_id',
        'handover_time',
        'physical_count_verified',
        'variance_myr',
        'variance_notes',
        'acknowledged_at',
        'yellow_variance',
    ];

    protected $casts = [
        'handover_time' => 'datetime',
        'physical_count_verified' => 'boolean',
        'variance_myr' => MoneyCast::class,
        'acknowledged_at' => 'datetime',
        'yellow_variance' => 'boolean',
    ];

    public function getSessionAttribute()
    {
        return $this->counterSession;
    }

    /**
     * @return BelongsTo<CounterSession, $this>
     */
    public function counterSession(): BelongsTo
    {
        return $this->belongsTo(CounterSession::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
