<?php

namespace App\Models;

use App\Enums\SystemHealthCheckStatus;
use App\Models\Bases\SystemModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SystemHealthCheck extends SystemModel
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
        'status' => SystemHealthCheckStatus::class,
    ];

    /**
     * Override time scope column to use checked_at instead of created_at.
     */
    protected function initializeHasTimeScopes(): void
    {
        $this->timeScopeColumn = 'checked_at';
    }

    /**
     * Scope for successful checks
     */
    public function scopeOk($query)
    {
        return $query->where('status', SystemHealthCheckStatus::Ok->value);
    }

    /**
     * Scope for warning checks
     */
    public function scopeWarning($query)
    {
        return $query->where('status', SystemHealthCheckStatus::Warning->value);
    }

    /**
     * Scope for critical checks
     */
    public function scopeCritical($query)
    {
        return $query->where('status', SystemHealthCheckStatus::Critical->value);
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
                return SystemHealthCheckStatus::Warning->value;
            }
            if ($check->status === SystemHealthCheckStatus::Critical) {
                return SystemHealthCheckStatus::Critical->value;
            }
        }

        foreach ($latestChecks as $check) {
            if ($check !== null && $check->status === SystemHealthCheckStatus::Warning) {
                return SystemHealthCheckStatus::Warning->value;
            }
        }

        return SystemHealthCheckStatus::Ok->value;
    }

    /**
     * Get status color class
     */
    public function getStatusColorClass(): string
    {
        return match ($this->status) {
            SystemHealthCheckStatus::Ok => 'green',
            SystemHealthCheckStatus::Warning => 'yellow',
            SystemHealthCheckStatus::Critical => 'red',
            default => 'gray',
        };
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute(): string
    {
        $statusValue = $this->status instanceof SystemHealthCheckStatus ? $this->status->value : $this->status;

        return match ($statusValue) {
            SystemHealthCheckStatus::Ok->value => 'status-active',
            SystemHealthCheckStatus::Warning->value => 'status-pending',
            SystemHealthCheckStatus::Critical->value => 'status-flagged',
            default => 'status-inactive',
        };
    }
}
