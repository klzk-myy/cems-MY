<?php

namespace App\Models;

use App\Enums\CounterSessionStatus;
use App\Enums\CounterStatus;
use App\Models\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property CounterStatus|null $status
 * @property int|null $branch_id
 * @property int|null $assigned_teller_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Counter extends BaseModel
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    /**
     * Width of the counters.code column - tombstoned codes must still fit.
     */
    protected const CODE_COLUMN_LENGTH = 10;

    protected $fillable = [
        'code',
        'name',
        'status',
        'branch_id',
    ];

    protected $casts = [
        'status' => CounterStatus::class,
    ];

    protected static function booted(): void
    {
        static::deleting(function (Counter $counter) {
            // code is UNIQUE but rows are soft-deleted: without a tombstone a
            // deleted counter would permanently burn its code for reuse.
            if ($counter->isForceDeleting()) {
                return;
            }

            $suffix = '_del_'.($counter->id ?? Str::random(6));
            $base = substr($counter->code ?? '', 0, max(0, self::CODE_COLUMN_LENGTH - strlen($suffix)));

            $counter->code = $base.$suffix;
            // Quiet save so the delete flow is not re-triggered.
            $counter->saveQuietly();
        });
    }

    /**
     * Get the route key for the model.
     * This allows route model binding to use 'code' instead of 'id'.
     */
    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function scopeActive($query)
    {
        return $query->where('status', CounterStatus::Active->value);
    }

    /**
     * @return HasMany<CounterSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(CounterSession::class);
    }

    /**
     * @return HasOne<CounterSession, $this>
     */
    public function currentSession(): HasOne
    {
        return $this->hasOne(CounterSession::class)
            ->where('session_date', now()->toDateString())
            ->where('status', CounterSessionStatus::Open->value)
            ->latest();
    }

    public static function findByCodeOrId(string|int $identifier): ?static
    {
        return static::where('code', $identifier)
            ->orWhere('id', $identifier)
            ->first();
    }
}
