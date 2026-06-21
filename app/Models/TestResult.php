<?php

namespace App\Models;

use App\Enums\TestResultStatus;
use App\Models\Bases\SystemModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestResult extends SystemModel
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

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

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
        'status' => TestResultStatus::class,
    ];

    /**
     * Scope for successful test runs
     */
    public function scopePassed($query)
    {
        return $query->where('status', TestResultStatus::Passed->value);
    }

    /**
     * Scope for failed test runs
     */
    public function scopeFailed($query)
    {
        return $query->where('status', TestResultStatus::Failed->value);
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
        $statusValue = $this->status instanceof TestResultStatus ? $this->status->value : $this->status;

        return match ($statusValue) {
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
        $statusValue = $this->status instanceof TestResultStatus ? $this->status->value : $this->status;

        return match ($statusValue) {
            'passed' => 'Passed',
            'failed' => 'Failed',
            'error' => 'Error',
            'running' => 'Running',
            default => 'Unknown',
        };
    }
}
