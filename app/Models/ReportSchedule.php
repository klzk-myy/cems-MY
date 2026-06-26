<?php

namespace App\Models;

use App\Enums\ReportType;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportSchedule extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'report_type',
        'cron_expression',
        'parameters',
        'is_active',
        'last_run_at',
        'next_run_at',
        'notification_recipients',
        'created_by',
    ];

    protected $casts = [
        'report_type' => ReportType::class,
        'parameters' => 'array',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'notification_recipients' => 'array',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reportRuns(): HasMany
    {
        return $this->hasMany(ReportRun::class, 'schedule_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function calculateNextRun(): ?\DateTime
    {
        if (empty($this->cron_expression)) {
            return null;
        }

        try {
            $cron = new CronExpression($this->cron_expression);

            return $cron->getNextRunDate();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function updateNextRun(): void
    {
        $this->next_run_at = $this->calculateNextRun();
        $this->save();
    }

    public function isDue(): bool
    {
        return $this->is_active
            && $this->next_run_at !== null
            && now()->isAfter($this->next_run_at);
    }

    public static function getReportTypes(): array
    {
        return [
            ReportType::Msb2->value => ReportType::Msb2->label(),
            ReportType::Lmca->value => ReportType::Lmca->label(),
            ReportType::Qlvr->value => ReportType::Qlvr->label(),
            ReportType::Plr->value => ReportType::Plr->label(),
        ];
    }

    public function getFriendlySchedule(): string
    {
        return match ($this->cron_expression) {
            '0 0 * * *' => 'Daily at midnight',
            '0 0 1 * *' => 'Monthly on the 1st',
            '0 0 * * 1' => 'Weekly on Monday',
            '0 0 1 1,4,7,10 *' => 'Quarterly (1st of Jan, Apr, Jul, Oct)',
            default => $this->cron_expression,
        };
    }
}
