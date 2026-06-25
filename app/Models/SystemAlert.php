<?php

namespace App\Models;

use App\Enums\SystemAlertLevel;
use App\Models\Bases\SystemModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemAlert extends SystemModel
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
        'level' => SystemAlertLevel::class,
    ];

    /**
     * Scope for info level alerts
     */
    public function scopeInfo($query)
    {
        return $query->where('level', SystemAlertLevel::Info->value);
    }

    /**
     * Scope for warning level alerts
     */
    public function scopeWarning($query)
    {
        return $query->where('level', SystemAlertLevel::Warning->value);
    }

    /**
     * Scope for critical level alerts
     */
    public function scopeCritical($query)
    {
        return $query->where('level', SystemAlertLevel::Critical->value);
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
        $level = SystemAlertLevel::tryFrom($minLevel) ?? SystemAlertLevel::Info;

        return $query->where(function ($q) use ($level) {
            $q->where('level', $level->value);
            if ($level !== SystemAlertLevel::Critical) {
                $q->orWhere('level', SystemAlertLevel::Critical->value);
            }
            if ($level !== SystemAlertLevel::Warning && $level !== SystemAlertLevel::Critical) {
                $q->orWhere('level', SystemAlertLevel::Warning->value);
            }
        });
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
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * Get status color class
     */
    public function getStatusColorClass(): string
    {
        return match ($this->level) {
            SystemAlertLevel::Critical => 'red',
            SystemAlertLevel::Warning => 'yellow',
            SystemAlertLevel::Info => 'blue',
            default => 'gray',
        };
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute(): string
    {
        $level = $this->level instanceof SystemAlertLevel ? $this->level : SystemAlertLevel::tryFrom($this->level) ?? SystemAlertLevel::Info;

        return match ($level) {
            SystemAlertLevel::Critical => 'status-flagged',
            SystemAlertLevel::Warning => 'status-pending',
            SystemAlertLevel::Info => 'status-active',
            default => 'status-inactive',
        };
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        $level = $this->level instanceof SystemAlertLevel ? $this->level : SystemAlertLevel::tryFrom($this->level) ?? SystemAlertLevel::Info;

        return match ($level) {
            SystemAlertLevel::Critical => 'Critical',
            SystemAlertLevel::Warning => 'Warning',
            SystemAlertLevel::Info => 'Info',
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
