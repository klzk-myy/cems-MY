<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'level',
        'message',
        'acknowledged_at',
        'acknowledged_by',
        'source',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Alert level constants
     */
    public const LEVEL_INFO = 'info';

    public const LEVEL_WARNING = 'warning';

    public const LEVEL_CRITICAL = 'critical';

    /**
     * Scope for info level alerts
     */
    public function scopeInfo($query)
    {
        return $query->where('level', self::LEVEL_INFO);
    }

    /**
     * Scope for warning level alerts
     */
    public function scopeWarning($query)
    {
        return $query->where('level', self::LEVEL_WARNING);
    }

    /**
     * Scope for critical level alerts
     */
    public function scopeCritical($query)
    {
        return $query->where('level', self::LEVEL_CRITICAL);
    }

    /**
     * Scope for unacknowledged alerts
     */
    public function scopeUnacknowledged($query)
    {
        return $query->whereNull('acknowledged_at');
    }

    /**
     * Scope for acknowledged alerts
     */
    public function scopeAcknowledged($query)
    {
        return $query->whereNotNull('acknowledged_at');
    }

    /**
     * Scope for alerts by minimum level
     */
    public function scopeMinLevel($query, string $minLevel)
    {
        $levels = [
            self::LEVEL_INFO => 1,
            self::LEVEL_WARNING => 2,
            self::LEVEL_CRITICAL => 3,
        ];

        $minLevelValue = $levels[$minLevel] ?? 1;

        $allowedLevels = array_filter($levels, function ($level) use ($minLevelValue) {
            return $level >= $minLevelValue;
        });

        return $query->whereIn('level', array_keys($allowedLevels));
    }

    /**
     * Scope for latest alerts first
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Scope for alerts from today
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope for alerts within date range
     */
    public function scopeBetweenDates($query, string $from, string $to)
    {
        return $query->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
    }

    /**
     * Acknowledge the alert
     */
    public function acknowledge(int $userId): void
    {
        $this->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $userId,
        ]);
    }

    /**
     * Check if alert is acknowledged
     */
    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }

    /**
     * Get the user who acknowledged the alert
     */
    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * Get status color class
     */
    public function getStatusColorClass(): string
    {
        return match ($this->level) {
            self::LEVEL_CRITICAL => 'red',
            self::LEVEL_WARNING => 'yellow',
            self::LEVEL_INFO => 'blue',
            default => 'gray',
        };
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->level) {
            self::LEVEL_CRITICAL => 'status-flagged',
            self::LEVEL_WARNING => 'status-pending',
            self::LEVEL_INFO => 'status-active',
            default => 'status-inactive',
        };
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->level) {
            self::LEVEL_CRITICAL => 'Critical',
            self::LEVEL_WARNING => 'Warning',
            self::LEVEL_INFO => 'Info',
            default => 'Unknown',
        };
    }

    /**
     * Get unacknowledged alert count by level
     */
    public static function getUnacknowledgedCounts(): array
    {
        return [
            'critical' => self::critical()->unacknowledged()->count(),
            'warning' => self::warning()->unacknowledged()->count(),
            'info' => self::info()->unacknowledged()->count(),
            'total' => self::unacknowledged()->count(),
        ];
    }

    /**
     * Get the most recent unacknowledged alerts
     */
    public static function getRecentUnacknowledged(int $limit = 10): array
    {
        return self::unacknowledged()
            ->latest()
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
