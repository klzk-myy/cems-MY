<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $counter_id
 * @property int $session_id
 * @property int $teller_id
 * @property string $reason
 * @property Carbon $closed_at
 * @property int|null $acknowledged_by
 * @property Carbon|null $acknowledged_at
 * @property-read Counter $counter
 * @property-read CounterSession $session
 * @property-read User $teller
 * @property-read User|null $acknowledgedBy
 */
class EmergencyClosure extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'counter_id',
        'session_id',
        'teller_id',
        'reason',
        'closed_at',
        'acknowledged_by',
        'acknowledged_at',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Counter, $this>
     */
    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class);
    }

    /**
     * @return BelongsTo<CounterSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CounterSession::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teller_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
