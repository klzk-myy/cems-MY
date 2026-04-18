<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportGenerated extends Model
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
    ];

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('report_type', $type);
    }

    public function scopeInPeriod($query, string $start, string $end)
    {
        return $query->whereBetween('period_start', [$start, $end]);
    }

    public function scopeLatestVersion($query, string $reportType, $periodStart)
    {
        return $query->where('report_type', $reportType)
            ->where('period_start', $periodStart)
            ->orderBy('version', 'desc');
    }

    public function isLatestVersion(): bool
    {
        $latest = static::where('report_type', $this->report_type)
            ->where('period_start', $this->period_start)
            ->where('status', '!=', 'Archived')
            ->max('version');

        return $this->version === $latest;
    }
}
