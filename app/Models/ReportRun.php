<?php

namespace App\Models;

use App\Enums\ReportRunStatus;
use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ReportRun extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'report_type',
        'parameters',
        'status',
        'started_at',
        'completed_at',
        'file_path',
        'generated_by',
        'row_count',
        'error_message',
        'downloaded_count',
    ];

    protected $casts = [
        'parameters' => 'array',
        'status' => ReportRunStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'row_count' => 'integer',
        'downloaded_count' => 'integer',
        'report_type' => ReportType::class,
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ReportSchedule::class, 'schedule_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', ReportRunStatus::Completed);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', ReportRunStatus::Failed);
    }

    public function getDownloadUrl(): ?string
    {
        if (empty($this->file_path)) {
            return null;
        }

        if (! Storage::exists($this->file_path)) {
            return null;
        }

        return route('compliance.reporting.history.download', ['id' => $this->id]);
    }

    public function getDurationInSeconds(): ?int
    {
        if ($this->started_at === null || $this->completed_at === null) {
            return null;
        }

        return $this->completed_at->diffInSeconds($this->started_at);
    }

    public function markAsRunning(): void
    {
        $this->status = ReportRunStatus::Running;
        $this->started_at = now();
        $this->save();
    }

    public function markAsCompleted(string $filePath, int $rowCount): void
    {
        $this->status = ReportRunStatus::Completed;
        $this->completed_at = now();
        $this->file_path = $filePath;
        $this->row_count = $rowCount;
        $this->save();
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->status = ReportRunStatus::Failed;
        $this->completed_at = now();
        $this->error_message = $errorMessage;
        $this->save();
    }
}
