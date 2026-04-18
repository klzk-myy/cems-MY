<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Backup Log Model
 * Tracks backup history, sizes, and statuses for CEMS-MY system
 */
class BackupLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'backup_name',
        'backup_type',
        'disk',
        'file_path',
        'file_size',
        'checksum',
        'encryption_status',
        'status',
        'started_at',
        'completed_at',
        'error_message',
        'metadata',
        'verified_at',
        'verification_status',
        'verification_error',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'encryption_status' => 'boolean',
        'verification_status' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'verified_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $dates = [
        'started_at',
        'completed_at',
        'verified_at',
    ];

    /**
     * Backup status constants
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_VERIFICATION_FAILED = 'verification_failed';

    /**
     * Backup type constants
     */
    public const TYPE_DATABASE = 'database';

    public const TYPE_FILES = 'files';

    public const TYPE_FULL = 'full';

    public const TYPE_ARCHIVE = 'archive';

    public const TYPE_MANUAL = 'manual';

    /**
     * Disk constants
     */
    public const DISK_LOCAL = 'local';

    public const DISK_S3 = 's3';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: Recent backups
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('started_at', '>=', now()->subDays($days));
    }

    /**
     * Scope: Completed backups
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope: Failed backups
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope: Verified backups
     */
    public function scopeVerified($query)
    {
        return $query->where('verification_status', true);
    }

    /**
     * Scope: By type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('backup_type', $type);
    }

    /**
     * Scope: By disk
     */
    public function scopeByDisk($query, string $disk)
    {
        return $query->where('disk', $disk);
    }

    /**
     * Get duration in seconds
     */
    public function getDurationAttribute(): ?int
    {
        if ($this->started_at && $this->completed_at) {
            return $this->completed_at->diffInSeconds($this->started_at);
        }

        return null;
    }

    /**
     * Format file size
     */
    public function getFormattedSizeAttribute(): string
    {
        if ($this->file_size === null) {
            return 'N/A';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $this->file_size;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2).' '.$units[$unitIndex];
    }

    /**
     * Mark backup as completed
     */
    public function markAsCompleted(string $filePath, int $fileSize, ?string $checksum = null): self
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'checksum' => $checksum,
            'completed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark backup as failed
     */
    public function markAsFailed(string $errorMessage): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark backup as verified
     */
    public function markAsVerified(bool $success = true, ?string $error = null): self
    {
        $this->update([
            'verification_status' => $success,
            'verified_at' => now(),
            'verification_error' => $error,
            'status' => $success ? self::STATUS_VERIFIED : self::STATUS_VERIFICATION_FAILED,
        ]);

        return $this;
    }

    /**
     * Check if backup is successful
     */
    public function isSuccessful(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_VERIFIED], true);
    }

    /**
     * Check if backup is verified
     */
    public function isVerified(): bool
    {
        return $this->verification_status === true && $this->status === self::STATUS_VERIFIED;
    }

    /**
     * Get latest successful backup
     */
    public static function latestSuccessful(?string $type = null): ?self
    {
        $query = self::completed()->orderByDesc('completed_at');

        if ($type) {
            $query->byType($type);
        }

        return $query->first();
    }

    /**
     * Get backup statistics
     */
    public static function getStatistics(int $days = 30): array
    {
        $recent = self::recent($days);

        return [
            'total_count' => $recent->count(),
            'successful_count' => (clone $recent)->completed()->count(),
            'failed_count' => (clone $recent)->failed()->count(),
            'verified_count' => (clone $recent)->verified()->count(),
            'total_size' => (clone $recent)->completed()->sum('file_size'),
            'average_duration' => (clone $recent)
                ->completed()
                ->whereNotNull('started_at')
                ->whereNotNull('completed_at')
                ->get()
                ->avg(fn ($log) => $log->duration),
        ];
    }
}
