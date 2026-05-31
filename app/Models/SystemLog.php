<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
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
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'severity' => 'string',
    ];

    /**
     * Scope by severity
     */
    public function scopeSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope by severity level (includes all equal or higher)
     */
    public function scopeSeverityLevel($query, string $minSeverity)
    {
        $levels = ['INFO' => 1, 'WARNING' => 2, 'ERROR' => 3, 'CRITICAL' => 4];
        $minLevel = $levels[$minSeverity] ?? 1;

        $severityList = array_filter($levels, function ($level) use ($minLevel) {
            return $level >= $minLevel;
        });

        return $query->whereIn('severity', array_keys($severityList));
    }

    /**
     * Scope by date range
     */
    public function scopeBetweenDates($query, string $from, string $to)
    {
        return $query->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
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
     * Get severity color class
     */
    public function getSeverityColor(): string
    {
        return match ($this->severity) {
            'CRITICAL' => 'red',
            'ERROR' => 'orange',
            'WARNING' => 'yellow',
            default => 'blue',
        };
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
