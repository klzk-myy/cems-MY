<?php

namespace App\Services;

use App\Enums\ReportStatus;
use App\Events\ReportGenerated;
use App\Models\ReportRun;
use App\Models\ReportSchedule;
use Illuminate\Support\Facades\Storage;

/**
 * Service for managing report scheduling and execution.
 * Handles creating schedules, running reports, and tracking history.
 */
class ReportSchedulingService
{
    public function __construct(
        protected ReportingService $reportingService,
    ) {}

    /**
     * Generate a report and track the run.
     */
    public function generateReport(
        string $type,
        array $params,
        int $userId,
        ?int $scheduleId = null
    ): ReportRun {
        $reportRun = ReportRun::create([
            'schedule_id' => $scheduleId,
            'report_type' => $type,
            'parameters' => $params,
            'status' => ReportStatus::Running,
            'started_at' => now(),
            'generated_by' => $userId,
        ]);

        try {
            $filePath = $this->executeReport($type, $params);

            $meta = $this->getFileMeta($filePath);

            $reportRun->markAsCompleted($filePath, $meta['row_count']);

            event(new ReportGenerated($reportRun));

            return $reportRun;
        } catch (\Exception $e) {
            $reportRun->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute the actual report generation.
     */
    protected function executeReport(string $type, array $params): string
    {
        return match ($type) {
            'msb2' => $this->reportingService->generateMSB2($params['date'] ?? now()->toDateString()),
            'lctr' => $this->reportingService->generateFormLMCACsv($params['month'] ?? now()->format('Y-m')),
            'lmca' => $this->reportingService->generateFormLMCACsv($params['month'] ?? now()->format('Y-m')),
            'qlvr' => $this->reportingService->generateQuarterlyLargeValueCsv($params['quarter'] ?? now()->format('Y').'-Q'.ceil(now()->month / 3)),
            'position_limit' => $this->reportingService->generatePositionLimitCsv(),
            default => throw new \InvalidArgumentException("Unknown report type: $type"),
        };
    }

    /**
     * Get report history with filters.
     */
    public function getReportHistory(array $filters = []): \Illuminate\Database\Eloquent\Builder
    {
        $query = ReportRun::with(['generatedBy', 'schedule'])
            ->orderByDesc('created_at');

        if (! empty($filters['report_type'])) {
            $query->where('report_type', $filters['report_type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        if (! empty($filters['generated_by'])) {
            $query->where('generated_by', $filters['generated_by']);
        }

        return $query;
    }

    /**
     * Get dashboard summary.
     */
    public function getDashboardSummary(): array
    {
        $totalRuns = ReportRun::count();
        $successfulRuns = ReportRun::successful()->count();
        $failedRuns = ReportRun::failed()->count();
        $scheduledRuns = ReportRun::where('status', ReportStatus::Scheduled)->count();

        $recentRuns = ReportRun::with('generatedBy')
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        $upcomingSchedules = ReportSchedule::active()
            ->orderBy('next_run_at')
            ->take(5)
            ->get();

        $avgDuration = ReportRun::successful()
            ->whereNotNull('completed_at')
            ->whereNotNull('started_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_duration')
            ->value('avg_duration');

        return [
            'total_runs' => $totalRuns,
            'successful_runs' => $successfulRuns,
            'failed_runs' => $failedRuns,
            'scheduled_runs' => $scheduledRuns,
            'success_rate' => $totalRuns > 0 ? round(($successfulRuns / $totalRuns) * 100, 1) : 100,
            'average_duration_seconds' => round($avgDuration ?? 0, 1),
            'recent_runs' => $recentRuns->map(fn ($r) => [
                'id' => $r->id,
                'type' => $r->report_type,
                'status' => $r->status->value,
                'generated_by' => $r->generatedBy?->name,
                'created_at' => $r->created_at->toIso8601String(),
            ]),
            'upcoming_schedules' => $upcomingSchedules->map(fn ($s) => [
                'id' => $s->id,
                'type' => $s->report_type,
                'next_run' => $s->next_run_at?->toIso8601String(),
            ]),
        ];
    }

    /**
     * Create a report schedule.
     */
    public function createSchedule(array $data): ReportSchedule
    {
        $schedule = ReportSchedule::create([
            'report_type' => $data['report_type'],
            'cron_expression' => $data['cron_expression'],
            'parameters' => $data['parameters'] ?? [],
            'is_active' => $data['is_active'] ?? true,
            'notification_recipients' => $data['notification_recipients'] ?? [],
            'created_by' => $data['created_by'],
            'next_run_at' => null,
        ]);

        $schedule->updateNextRun();

        return $schedule;
    }

    /**
     * Update a report schedule.
     */
    public function updateSchedule(ReportSchedule $schedule, array $data): ReportSchedule
    {
        if (isset($data['cron_expression'])) {
            $schedule->cron_expression = $data['cron_expression'];
        }

        if (isset($data['parameters'])) {
            $schedule->parameters = $data['parameters'];
        }

        if (isset($data['is_active'])) {
            $schedule->is_active = $data['is_active'];
        }

        if (isset($data['notification_recipients'])) {
            $schedule->notification_recipients = $data['notification_recipients'];
        }

        $schedule->save();
        $schedule->updateNextRun();

        return $schedule;
    }

    /**
     * Get preview data for a report type.
     */
    public function getPreviewData(string $type, array $params): array
    {
        return match ($type) {
            'msb2' => $this->reportingService->generateMSB2Data($params['date'] ?? now()->toDateString()),
            'lctr' => $this->reportingService->generateLCTRData($params['month'] ?? now()->format('Y-m')),
            'lmca' => $this->reportingService->generateFormLMCA($params['month'] ?? now()->format('Y-m')),
            'qlvr' => $this->reportingService->generateQuarterlyLargeValueReport($params['quarter'] ?? now()->format('Y').'-Q'.ceil(now()->month / 3)),
            'position_limit' => $this->reportingService->generatePositionLimitReport(),
            default => throw new \InvalidArgumentException("Unknown report type: $type"),
        };
    }

    /**
     * Get deadline calendar for reports.
     */
    public function getDeadlineCalendar(): array
    {
        $today = now();
        $deadlines = [];

        $strDeadlines = \App\Models\StrDraft::pending()
            ->whereNotNull('filing_deadline')
            ->get()
            ->map(fn ($draft) => [
                'type' => 'str',
                'reference' => $draft->id,
                'deadline' => $draft->filing_deadline->toDateString(),
                'urgency' => $this->calculateUrgency($draft->filing_deadline),
                'status' => $draft->status->value,
            ]);

        $scheduledReports = ReportSchedule::active()
            ->whereNotNull('next_run_at')
            ->get()
            ->map(fn ($schedule) => [
                'type' => 'scheduled_report',
                'reference' => $schedule->id,
                'deadline' => $schedule->next_run_at->toDateString(),
                'urgency' => $this->calculateUrgency($schedule->next_run_at),
                'report_type' => $schedule->report_type,
            ]);

        return $strDeadlines->merge($scheduledReports)
            ->sortBy('deadline')
            ->values()
            ->toArray();
    }

    /**
     * Get KPI metrics.
     */
    public function getKpiMetrics(): array
    {
        $startOfMonth = now()->startOfMonth();

        $completedRuns = ReportRun::where('status', ReportStatus::Completed)->get();

        $flagResolutionTime = $this->calculateAvgFlagResolutionTime();
        $strTimeliness = $this->calculateStrTimeliness();
        $eddCompletionRate = $this->calculateEddCompletionRate();
        $reportsOnSchedule = $this->calculateReportsOnSchedule();

        return [
            'flag_resolution_avg_hours' => round($flagResolutionTime, 1),
            'str_on_time_percent' => round($strTimeliness, 1),
            'edd_completion_rate_percent' => round($eddCompletionRate, 1),
            'reports_on_schedule_percent' => round($reportsOnSchedule, 1),
            'period' => 'Last 30 days',
        ];
    }

    protected function getFileMeta(string $filePath): array
    {
        if (! Storage::exists($filePath)) {
            return ['row_count' => 0];
        }

        $content = Storage::get($filePath);
        $lines = explode("\n", trim($content));
        $rowCount = max(0, count($lines) - 1);

        return ['row_count' => $rowCount];
    }

    protected function calculateUrgency(\DateTime $date): string
    {
        $diffHours = now()->diffInHours($date, false);

        if ($diffHours < 0) {
            return 'overdue';
        } elseif ($diffHours <= 24) {
            return 'critical';
        } elseif ($diffHours <= 72) {
            return 'warning';
        }

        return 'normal';
    }

    protected function calculateAvgFlagResolutionTime(): float
    {
        $resolvedFlags = \App\Models\FlaggedTransaction::whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->subDays(30))
            ->get();

        if ($resolvedFlags->isEmpty()) {
            return 0;
        }

        $totalMinutes = 0;
        foreach ($resolvedFlags as $flag) {
            $totalMinutes += $flag->resolved_at->diffInMinutes($flag->created_at);
        }

        return ($totalMinutes / $resolvedFlags->count()) / 60;
    }

    protected function calculateStrTimeliness(): float
    {
        $totalStrs = \App\Models\StrReport::where('created_at', '>=', now()->subDays(30))->count();

        if ($totalStrs === 0) {
            return 100;
        }

        $onTimeStrs = \App\Models\StrReport::where('created_at', '>=', now()->subDays(30))
            ->get()
            ->filter(fn ($str) => $str->filing_deadline && $str->created_at->lte($str->filing_deadline))
            ->count();

        return ($onTimeStrs / $totalStrs) * 100;
    }

    protected function calculateEddCompletionRate(): float
    {
        $totalEdds = \App\Models\EnhancedDiligenceRecord::where('created_at', '>=', now()->subDays(30))->count();

        if ($totalEdds === 0) {
            return 100;
        }

        $completedEdds = \App\Models\EnhancedDiligenceRecord::where('created_at', '>=', now()->subDays(30))
            ->whereIn('status', ['approved', 'rejected'])
            ->count();

        return ($completedEdds / $totalEdds) * 100;
    }

    protected function calculateReportsOnSchedule(): float
    {
        $totalSchedules = ReportSchedule::active()->count();

        if ($totalSchedules === 0) {
            return 100;
        }

        $recentRuns = ReportRun::whereNotNull('schedule_id')
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        $successfulScheduled = $recentRuns->filter(fn ($r) => $r->schedule_id && $r->status === ReportStatus::Completed
        )->count();

        return ($successfulScheduled / $totalSchedules) * 100;
    }
}
