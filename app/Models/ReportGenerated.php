<?php

namespace App\Models;

use App\Enums\ReportGeneratedStatus;
use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $report_type
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property int $generated_by
 * @property Carbon $generated_at
 * @property string|null $file_path
 * @property string $file_format 'CSV', 'PDF', 'XLSX'
 * @property string|null $status 'Generated', 'Submitted', 'Pending', 'Archived'
 * @property int $version
 * @property string|null $notes
 * @property Carbon|null $submitted_at
 * @property int|null $submitted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ReportGenerated extends BaseModel
{
    use HasFactory;

    protected $table = 'reports_generated';

    protected $fillable = [
        'report_type',
        'period_start',
        'period_end',
        'generated_by',
        'generated_at',
        'file_path',
        'file_format',
        'status',
        'submitted_at',
        'submitted_by',
        'version',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'generated_at' => 'datetime',
        'submitted_at' => 'datetime',
        'version' => 'integer',
        'status' => ReportGeneratedStatus::class,
        'report_type' => ReportType::class,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function scopeByType($query, ReportType|string $type)
    {
        return $query->where('report_type', $type);
    }

    public function scopeInPeriod($query, string $start, string $end)
    {
        return $query->whereBetween('period_start', [$start, $end]);
    }

    public function scopeLatestVersion($query, ReportType|string $reportType, $periodStart)
    {
        return $query->where('report_type', $reportType)
            ->where('period_start', $periodStart)
            ->orderBy('version', 'desc');
    }

    public function isLatestVersion(): bool
    {
        $latest = static::where('report_type', $this->report_type)
            ->where('period_start', $this->period_start)
            ->where('status', '!=', ReportGeneratedStatus::Archived->value)
            ->max('version');

        return $this->version === $latest;
    }
}
