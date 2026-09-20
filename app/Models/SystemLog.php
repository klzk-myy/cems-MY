<?php

namespace App\Models;

use App\Enums\SystemLogSeverity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $description
 * @property SystemLogSeverity $severity
 * @property string|null $entity_type
 * @property int|null $entity_id
 * @property array|null $old_values
 * @property array|null $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $session_id
 * @property string|null $previous_hash Tamper-evidence chain hash of the previous entry
 * @property string|null $entry_hash Tamper-evidence hash of this entry
 * @property string|null $seal_status 'quarantined' when the row is permanently unsealable
 * @property int $seal_attempts Failed seal sweep attempts before quarantine
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SystemLog extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'description',
        'severity',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'session_id',
        'previous_hash',
        'entry_hash',
        'seal_status',
        'seal_attempts',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'severity' => SystemLogSeverity::class,
        'seal_attempts' => 'integer',
    ];

    protected $hidden = [
        'session_id',
        'ip_address',
        'user_agent',
        'previous_hash',
        'entry_hash',
    ];

    /**
     * Scope by severity
     */
    public function scopeSeverity($query, SystemLogSeverity $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope by severity level (includes all equal or higher)
     */
    public function scopeSeverityLevel($query, SystemLogSeverity $minSeverity)
    {
        $minLevel = $minSeverity->level();

        $severities = array_filter(
            SystemLogSeverity::cases(),
            fn (SystemLogSeverity $severity): bool => $severity->level() >= $minLevel
        );

        return $query->whereIn('severity', $severities);
    }

    /**
     * Scope by date range
     */
    public function scopeBetweenDates($query, string $from, string $to)
    {
        return $query->whereBetween('created_at', [
            Carbon::parse($from)->startOfDay(),
            Carbon::parse($to)->endOfDay(),
        ]);
    }

    /**
     * Scope by action
     */
    public function scopeAction($query, string $action)
    {
        return $query->where('action', 'like', '%'.$action.'%');
    }

    /**
     * Scope by entity type
     */
    public function scopeEntityType($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
