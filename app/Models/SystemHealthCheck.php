<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemHealthCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'check_name',
        'status',
        'message',
        'checked_at',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    public const STATUS_OK = 'ok';

    public const STATUS_WARNING = 'warning';

    public const STATUS_CRITICAL = 'critical';

    /**
     * Scope for successful checks
     */
    public function scopeOk($query)
    {
        return $query->where('status', self::STATUS_OK);
    }

    /**
     * Scope for warning checks
     */
    public function scopeWarning($query)
    {
        return $query->where('status', self::STATUS_WARNING);
    }

    /**
     * Scope for critical checks
     */
    public function scopeCritical($query)
    {
        return $query->where('status', self::STATUS_CRITICAL);
    }

    /**
     * Scope for latest checks first
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('checked_at', 'desc');
    }

    /**
     * Scope for specific check name
     */
    public function scopeCheckName($query, string $name)
    {
        return $query->where('check_name', $name);
    }

    /**
     * Scope for recent checks (within last X minutes)
     */
    public function scopeRecent($query, int $minutes = 10)
    {
        return $query->where('checked_at', '>=', now()->subMinutes($minutes));
    }

    /**
     * Get the latest check for each check name
     */
    public static function getLatestChecks(): array
    {
        $checkNames = [
            'database',
            'cache',
            'queue',
            'disk_space',
            'memory',
            'tests',
        ];

        $results = [];
        foreach ($checkNames as $name) {
            $results[$name] = self::checkName($name)->latest()->first();
        }

        return $results;
    }

    /**
     * Get overall system status
     */
    public static function getOverallStatus(): string
    {
        $latestChecks = self::getLatestChecks();

        foreach ($latestChecks as $check) {
            if ($check === null) {
                return self::STATUS_WARNING;
            }
            if ($check->status === self::STATUS_CRITICAL) {
                return self::STATUS_CRITICAL;
            }
        }

        foreach ($latestChecks as $check) {
            if ($check !== null && $check->status === self::STATUS_WARNING) {
                return self::STATUS_WARNING;
            }
        }

        return self::STATUS_OK;
    }

    /**
     * Get status color class
     */
    public function getStatusColorClass(): string
    {
        return match ($this->status) {
            self::STATUS_OK => 'green',
            self::STATUS_WARNING => 'yellow',
            self::STATUS_CRITICAL => 'red',
            default => 'gray',
        };
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_OK => 'status-active',
            self::STATUS_WARNING => 'status-pending',
            self::STATUS_CRITICAL => 'status-flagged',
            default => 'status-inactive',
        };
    }
}
