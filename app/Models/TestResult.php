<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'run_id',
        'test_suite',
        'total_tests',
        'passed',
        'failed',
        'skipped',
        'assertions',
        'duration',
        'status',
        'output',
        'failures',
        'errors',
        'git_branch',
        'git_commit',
        'executed_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'duration' => 'float',
        'total_tests' => 'integer',
        'passed' => 'integer',
        'failed' => 'integer',
        'skipped' => 'integer',
        'assertions' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failures' => 'array',
        'errors' => 'array',
    ];

    /**
     * Scope for successful test runs
     */
    public function scopePassed($query)
    {
        return $query->where('status', 'passed');
    }

    /**
     * Scope for failed test runs
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for latest runs first
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Scope for specific test suite
     */
    public function scopeSuite($query, string $suite)
    {
        return $query->where('test_suite', $suite);
    }

    /**
     * Calculate pass rate percentage
     */
    public function getPassRateAttribute(): float
    {
        if ($this->total_tests === 0) {
            return 0.0;
        }

        return round(($this->passed / $this->total_tests) * 100, 2);
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute(): string
    {
        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;

        if ($minutes > 0) {
            return "{$minutes}m {$seconds}s";
        }

        return "{$seconds}s";
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'passed' => 'status-active',
            'failed' => 'status-flagged',
            'error' => 'status-error',
            'running' => 'status-pending',
            default => 'status-inactive',
        };
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'passed' => 'Passed',
            'failed' => 'Failed',
            'error' => 'Error',
            'running' => 'Running',
            default => 'Unknown',
        };
    }
}
