<?php

namespace App\Services\Reporting;

use App\Enums\EddStatus;
use App\Enums\ReportGeneratedStatus;
use App\Enums\ReportRunStatus;
use App\Enums\ReportType;
use App\Events\ReportGenerated;
use App\Models\Compliance\EnhancedDiligenceRecord;
use App\Models\Compliance\FlaggedTransaction;
use App\Models\ReportRun;
use App\Models\ReportSchedule;
use App\Services\AuditService;
use App\Support\ActorContext;
use App\ValueObjects\Quarter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service for managing report scheduling and execution.
 * Handles creating schedules, running reports, and tracking history.
 */
class ReportSchedulingService
{
    public function __construct(
        protected ReportingService $reportingService,
        protected AuditService $auditService,
    ) {}

    /**
     * Generate a report and track the run.
     */
    public function generateReport(
        ReportType $type,
        array $params,
        int $userId,
        ?int $scheduleId = null
    ): ReportRun {
        $reportRun = ReportRun::create([
            'schedule_id' => $scheduleId,
            'report_type' => $type,
            'parameters' => $params,
            'status' => ReportRunStatus::Running,
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
     * Process every active schedule whose next_run_at is due.
     *
     * Each schedule is isolated: a failing generator marks its run failed and
     * still advances the schedule's next_run_at without blocking other rows.
     *
     * @return array{due: int, processed: int, failed: int}
     */
    public function processDueSchedules(): array
    {
        $due = ReportSchedule::active()
            ->with('createdBy')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->get();

        $processed = 0;
        $failed = 0;

        foreach ($due as $schedule) {
            try {
                $this->processSchedule($schedule);
                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Report schedule processing failed', [
                    'schedule_id' => $schedule->id,
                    'report_type' => $schedule->report_type?->value,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['due' => $due->count(), 'processed' => $processed, 'failed' => $failed];
    }

    /**
     * Run one due schedule: generate the report, persist the run outcome,
     * register the artifact in reports_generated (so archival sweeps it),
     * write an audit trail entry and advance next_run_at.
     */
    protected function processSchedule(ReportSchedule $schedule): void
    {
        $type = $schedule->report_type;
        $params = $this->resolveRunParameters($schedule);
        $period = $this->resolvePeriodString($type, $params);

        $run = ReportRun::create([
            'schedule_id' => $schedule->id,
            'report_type' => $type,
            'parameters' => $params,
            'status' => ReportRunStatus::Running,
            'started_at' => now(),
            'generated_by' => $schedule->created_by ?? config('cems.system_user_id', 1),
        ]);

        try {
            $filePath = $this->reportingService->generateReport($type->value, $period);

            $meta = $this->getFileMeta($filePath);

            $run->markAsCompleted($filePath, $meta['row_count']);

            [$periodStart, $periodEnd] = $this->resolvePeriodBounds($type, $params);

            $artifact = $this->reportingService->recordGeneratedReport(
                $type,
                $periodStart,
                $periodEnd,
                ReportGeneratedStatus::Generated,
                'CSV'
            );
            $artifact->file_path = $filePath;
            $artifact->save();

            event(new ReportGenerated($run));

            $this->auditService->logRegulatoryReportEvent('regulatory_report_generated', $artifact->id, [
                'user_id' => $schedule->created_by ?? config('cems.system_user_id', 1),
                'actor' => $schedule->createdBy !== null ? $schedule->createdBy->username : 'system',
                'source' => 'schedule',
                'schedule_id' => $schedule->id,
                'report_run_id' => $run->id,
                'new_values' => [
                    'report_type' => $type->value,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'file_id' => $artifact->id,
                    'file_path' => $filePath,
                ],
            ]);
        } catch (\Throwable $e) {
            $run->markAsFailed($e->getMessage());

            throw $e;
        } finally {
            $schedule->last_run_at = now();
            $schedule->updateNextRun();
        }
    }

    /**
     * Merge stored schedule parameters over sensible period defaults derived
     * from the report type cadence (monthly types cover the previous month,
     * daily types yesterday, quarterly the previous quarter).
     *
     * @return array<string, mixed>
     */
    protected function resolveRunParameters(ReportSchedule $schedule): array
    {
        $defaults = match ($schedule->report_type) {
            ReportType::Msb2 => ['date' => now()->subDay()->toDateString()],
            ReportType::Lmca => ['month' => now()->subMonthNoOverflow()->format('Y-m')],
            ReportType::Qlvr => ['quarter' => now()->subMonths(3)->startOfQuarter()->format('Y-m')],
            ReportType::TrialBalance,
            ReportType::MonthEnd,
            ReportType::ProfitLoss,
            ReportType::BalanceSheet => ['period' => now()->subMonthNoOverflow()->format('Y-m')],
            ReportType::Plr => [],
        };

        return array_merge($defaults, $schedule->parameters ?? []);
    }

    /**
     * Normalize parameters into the single period string accepted by
     * ReportingService::generateReport().
     *
     * @param  array<string, mixed>  $params
     */
    protected function resolvePeriodString(ReportType $type, array $params): string
    {
        return match ($type) {
            ReportType::Msb2 => (string) ($params['date'] ?? now()->subDay()->toDateString()),
            ReportType::Lmca => (string) ($params['month'] ?? now()->subMonthNoOverflow()->format('Y-m')),
            ReportType::Qlvr => $this->normalizeQuarterToPeriod((string) ($params['quarter'] ?? '')),
            ReportType::Plr => now()->toDateString(),
            default => (string) ($params['period'] ?? now()->subMonthNoOverflow()->format('Y-m')),
        };
    }

    /**
     * generateReport() derives the quarter by parsing a date, so a stored
     * "Y-Qn" parameter is converted to that quarter's start date first.
     */
    protected function normalizeQuarterToPeriod(string $quarter): string
    {
        if ($quarter !== '' && preg_match('/^\d{4}-Q[1-4]$/', $quarter) === 1) {
            return Quarter::fromString($quarter)->startDate()->toDateString();
        }

        return $quarter !== '' ? $quarter : now()->subMonths(3)->startOfQuarter()->toDateString();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolvePeriodBounds(ReportType $type, array $params): array
    {
        return match ($type) {
            ReportType::Msb2 => $this->dayBounds((string) ($params['date'] ?? now()->subDay()->toDateString())),
            ReportType::Lmca => $this->monthBounds((string) ($params['month'] ?? now()->subMonthNoOverflow()->format('Y-m'))),
            ReportType::Qlvr => $this->quarterBounds((string) ($params['quarter'] ?? '')),
            ReportType::Plr => [now()->startOfDay(), now()->endOfDay()],
            default => $this->monthBounds((string) ($params['period'] ?? now()->subMonthNoOverflow()->format('Y-m'))),
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function dayBounds(string $date): array
    {
        $day = Carbon::parse($date);

        return [$day->copy()->startOfDay(), $day->copy()->endOfDay()];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function monthBounds(string $month): array
    {
        $monthDate = Carbon::parse($month)->startOfMonth();

        return [$monthDate->copy()->startOfDay(), $monthDate->copy()->endOfMonth()->endOfDay()];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function quarterBounds(string $quarter): array
    {
        if ($quarter !== '' && preg_match('/^\d{4}-Q[1-4]$/', $quarter) === 1) {
            $quarterVo = Quarter::fromString($quarter);

            return [Carbon::parse($quarterVo->startDate())->startOfDay(), Carbon::parse($quarterVo->endDate())->endOfDay()];
        }

        $start = Carbon::parse(
            $quarter !== '' ? $quarter : now()->subMonths(3)->startOfQuarter()->toDateString()
        )->startOfQuarter()->startOfDay();

        return [$start, $start->copy()->endOfQuarter()->endOfDay()];
    }

    /**
     * Execute the actual report generation.
     *
     * Every ReportType must have an arm here — an incomplete match throws
     * UnmatchError at runtime. The four ledger-backed reports delegate to
     * the canonical ReportingService dispatcher, which owns their period
     * semantics.
     */
    protected function executeReport(ReportType $type, array $params): string
    {
        return match ($type) {
            ReportType::Msb2 => $this->reportingService->generateMSB2($params['date'] ?? now()->toDateString()),
            ReportType::Lmca => $this->reportingService->generateFormLMCACsv($params['month'] ?? now()->format('Y-m')),
            ReportType::Qlvr => $this->reportingService->generateQuarterlyLargeValueCsv($params['quarter'] ?? now()->format('Y').'-Q'.ceil(now()->month / 3)),
            ReportType::Plr => $this->reportingService->generatePositionLimitCsv(),
            ReportType::TrialBalance => $this->reportingService->generateReport($type->value, $params['period'] ?? now()->toDateString()),
            ReportType::MonthEnd => $this->reportingService->generateReport($type->value, $params['period'] ?? now()->toDateString()),
            ReportType::ProfitLoss => $this->reportingService->generateReport($type->value, $params['period'] ?? now()->toDateString()),
            ReportType::BalanceSheet => $this->reportingService->generateReport($type->value, $params['period'] ?? now()->toDateString()),
        };
    }

    /**
     * Get dashboard summary.
     */
    public function getDashboardSummary(): array
    {
        $totalRuns = ReportRun::count();
        $successfulRuns = ReportRun::successful()->count();
        $failedRuns = ReportRun::failed()->count();
        $scheduledRuns = ReportRun::where('status', ReportRunStatus::Scheduled->value)->count();

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
            ->get()
            ->avg(fn ($run) => $run->started_at->diffInSeconds($run->completed_at));

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
                'generated_by' => $r->generatedBy?->username,
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
        $reportType = $data['report_type'] instanceof ReportType
            ? $data['report_type']
            : ReportType::from($data['report_type']);

        $schedule = ReportSchedule::create([
            'report_type' => $reportType,
            'cron_expression' => $data['cron_expression'],
            'parameters' => $data['parameters'] ?? [],
            'is_active' => $data['is_active'] ?? true,
            'notification_recipients' => $data['notification_recipients'] ?? [],
            'created_by' => $data['created_by'] ?? ActorContext::capture()->userId,
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
        if (isset($data['report_type'])) {
            $schedule->report_type = $data['report_type'] instanceof ReportType
                ? $data['report_type']
                : ReportType::from($data['report_type']);
        }

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
    public function getPreviewData(ReportType $type, array $params): array
    {
        return match ($type) {
            ReportType::Msb2 => $this->reportingService->generateMSB2Data($params['date'] ?? now()->toDateString()),
            ReportType::Lmca => $this->reportingService->generateFormLMCA($params['month'] ?? now()->format('Y-m')),
            ReportType::Qlvr => $this->reportingService->generateQuarterlyLargeValueReport($params['quarter'] ?? now()->format('Y').'-Q'.ceil(now()->month / 3)),
            ReportType::Plr => $this->reportingService->generatePositionLimitReport(),
        };
    }

    /**
     * Get deadline calendar for reports.
     */
    public function getDeadlineCalendar(): array
    {
        $scheduledReports = ReportSchedule::active()
            ->whereNotNull('next_run_at')
            ->get()
            ->map(fn ($schedule) => [
                'name' => $schedule->report_type,
                'due_date' => $schedule->next_run_at,
                'status' => $schedule->next_run_at->isPast() ? 'overdue' : 'upcoming',
            ]);

        return $scheduledReports
            ->sortBy('due_date')
            ->values()
            ->toArray();
    }

    /**
     * Get KPI metrics.
     */
    public function getKpiMetrics(): array
    {
        $flagResolutionTime = $this->calculateAvgFlagResolutionTime();
        $eddCompletionRate = $this->calculateEddCompletionRate();
        $reportsOnSchedule = $this->calculateReportsOnSchedule();

        return [
            ['value' => round($flagResolutionTime, 1).'h', 'label' => 'Avg Flag Resolution'],
            ['value' => round($eddCompletionRate, 1).'%', 'label' => 'EDD Completion Rate'],
            ['value' => round($reportsOnSchedule, 1).'%', 'label' => 'Reports On Schedule'],
        ];
    }

    protected function getFileMeta(string $filePath): array
    {
        if (! Storage::exists($filePath)) {
            return ['row_count' => 0];
        }

        // Stream the file line-by-line instead of reading the whole CSV into
        // memory just to count rows. fopen + fgets keeps memory flat even for
        // multi-megabyte report exports.
        $handle = fopen(Storage::path($filePath), 'r');
        if ($handle === false) {
            return ['row_count' => 0];
        }

        $lines = 0;
        while (fgets($handle) !== false) {
            $lines++;
        }
        fclose($handle);

        return ['row_count' => max(0, $lines - 1)];
    }

    protected function calculateAvgFlagResolutionTime(): float
    {
        // Compute the average resolution time in SQL — avoids hydrating every
        // resolved flag of the last 30 days into PHP memory just to diff two
        // timestamps. SQLite lacks AVG on a datetime subtraction, so compute
        // the per-flag minute diff withstrftime and average that.
        $avgMinutes = FlaggedTransaction::whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->subDays(30))
            ->selectRaw('AVG((julianday(resolved_at) - julianday(created_at)) * 24 * 60) as avg_minutes')
            ->value('avg_minutes');

        return $avgMinutes !== null ? round(((float) $avgMinutes) / 60, 1) : 0.0;
    }

    protected function calculateEddCompletionRate(): float
    {
        $base = EnhancedDiligenceRecord::where('created_at', '>=', now()->subDays(30));
        $totalEdds = (int) $base->count();

        if ($totalEdds === 0) {
            return 100.0;
        }

        // Conditional aggregate — one query instead of two.
        $completedEdds = (int) (clone $base)
            ->whereIn('status', [EddStatus::Approved->value, EddStatus::Rejected->value])
            ->count();

        return round(($completedEdds / $totalEdds) * 100, 1);
    }

    protected function calculateReportsOnSchedule(): float
    {
        $totalSchedules = (int) ReportSchedule::active()->count();

        if ($totalSchedules === 0) {
            return 100.0;
        }

        // Count in SQL instead of loading a week of runs into memory.
        $successfulScheduled = (int) ReportRun::whereNotNull('schedule_id')
            ->where('created_at', '>=', now()->subDays(7))
            ->where('status', ReportRunStatus::Completed->value)
            ->count();

        return round(($successfulScheduled / $totalSchedules) * 100, 1);
    }
}
