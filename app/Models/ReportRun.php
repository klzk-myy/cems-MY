<?php

namespace App\Models;

use App\Enums\ReportRunStatus;
use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int|null $schedule_id
 * @property ReportType $report_type
 * @property array<string, mixed>|null $parameters
 * @property ReportRunStatus $status
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property string|null $file_path
 * @property int $generated_by
 * @property int $row_count
 * @property string|null $error_message
 * @property-read ReportSchedule|null $schedule
 * @property-read User|null $generatedBy
 */
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
    ];

    protected $casts = [
        'parameters' => 'array',
        'status' => ReportRunStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'row_count' => 'integer',
        'report_type' => ReportType::class,
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ReportSchedule::class, 'schedule_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', ReportRunStatus::Completed->value);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', ReportRunStatus::Failed->value);
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

        return (int) $this->completed_at->diffInSeconds($this->started_at);
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
