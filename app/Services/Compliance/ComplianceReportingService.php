<?php

namespace App\Services\Compliance;

use App\Enums\ComplianceCaseStatus;
use App\Enums\EddStatus;
use App\Enums\FindingSeverity;
use App\Enums\StrStatus;
use App\Models\Compliance\ComplianceCase;
use App\Models\Compliance\ComplianceFinding;
use App\Models\Compliance\CustomerRiskProfile;
use App\Models\EnhancedDiligenceRecord;
use App\Models\ReportGenerated;
use App\Models\StrReport;
use App\Models\SystemLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service for compliance reporting and dashboard analytics.
 * Provides KPIs, calendar deadlines, case aging, and audit trail data.
 */
class ComplianceReportingService
{
    /**
     * BNM filing deadline in working days for STR (rolling).
     */
    public const STR_FILING_DEADLINE_DAYS = 3;

    /**
     * BNM filing deadline in working days for LCTR/LMCA (monthly).
     */
    public const MONTHLY_FILING_DEADLINE_DAYS = 7;

    /**
     * BNM filing deadline in working days for QLVR (quarterly).
     */
    public const QUARTERLY_FILING_DEADLINE_DAYS = 10;

    /**
     * Get dashboard KPIs including case summary, STR status, EDD status, findings, and risk distribution.
     */
    public function getDashboardKpis(): array
    {
        return [
            'case_summary' => $this->getCaseSummary(),
            'str_status' => $this->getStrStatusCounts(),
            'edd_status' => $this->getEddStatusCounts(),
            'open_findings_7_days' => $this->getFindingsBySeverityLast7Days(),
            'risk_distribution' => $this->getRiskDistribution(),
        ];
    }

    /**
     * Get case counts by status.
     */
    protected function getCaseSummary(): array
    {
        $cases = ComplianceCase::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        $counts = [];
        foreach ($cases as $case) {
            $statusValue = $case->status instanceof ComplianceCaseStatus
                ? $case->status->value
                : $case->status;
            $counts[$statusValue] = $case->count;
        }

        return [
            'open' => $counts[ComplianceCaseStatus::Open->value] ?? 0,
            'under_review' => $counts[ComplianceCaseStatus::UnderReview->value] ?? 0,
            'escalated' => $counts[ComplianceCaseStatus::Escalated->value] ?? 0,
            'closed' => $counts[ComplianceCaseStatus::Closed->value] ?? 0,
        ];
    }

    /**
     * Get STR counts by status category.
     */
    protected function getStrStatusCounts(): array
    {
        $all = StrReport::all();

        // Compare against enum objects since status is cast
        $pending = $all->whereIn('status', [
            StrStatus::Draft,
            StrStatus::PendingReview,
            StrStatus::PendingApproval,
        ])->count();

        $today = now()->toDateString();
        $dueToday = $all->filter(function ($str) use ($today) {
            $status = $str->status instanceof StrStatus ? $str->status : StrStatus::tryFrom($str->status);

            return $str->filing_deadline &&
                $str->filing_deadline->toDateString() === $today &&
                ! in_array($status, [StrStatus::Submitted, StrStatus::Acknowledged]);
        })->count();

        $overdue = $all->filter(function ($str) {
            $status = $str->status instanceof StrStatus ? $str->status : StrStatus::tryFrom($str->status);

            return $str->isOverdue() &&
                ! in_array($status, [StrStatus::Submitted, StrStatus::Acknowledged]);
        })->count();

        $filed = $all->whereIn('status', [
            StrStatus::Submitted,
            StrStatus::Acknowledged,
        ])->count();

        return [
            'pending' => $pending,
            'due_today' => $dueToday,
            'overdue' => $overdue,
            'filed' => $filed,
        ];
    }

    /**
     * Get EDD record counts by status category.
     * Note: EnhancedDiligenceRecord doesn't have expiry_date column.
     * Due <30 days is calculated based on created_at for pending records.
     */
    protected function getEddStatusCounts(): array
    {
        $all = EnhancedDiligenceRecord::all();
        $now = now();
        $thirtyDaysAgo = $now->copy()->subDays(30);

        // Active = not closed (not Expired, Rejected, or Approved)
        $active = $all->filter(function ($edd) {
            $status = $edd->status instanceof EddStatus ? $edd->status->value : $edd->status;

            return ! in_array($status, [
                EddStatus::Expired->value,
                EddStatus::Rejected->value,
                EddStatus::Approved->value,
            ]);
        })->count();

        // Due <30 days = pending records created more than 30 days ago
        // (assuming they should have been completed by now)
        $due30Days = $all->filter(function ($edd) use ($thirtyDaysAgo) {
            $status = $edd->status instanceof EddStatus ? $edd->status->value : $edd->status;
            if (in_array($status, [EddStatus::Expired->value, EddStatus::Rejected->value, EddStatus::Approved->value])) {
                return false;
            }
            $created = $edd->created_at instanceof Carbon ? $edd->created_at : Carbon::parse($edd->created_at);

            return $created->lt($thirtyDaysAgo);
        })->count();

        $expired = $all->filter(function ($edd) {
            $status = $edd->status instanceof EddStatus ? $edd->status->value : $edd->status;

            return $status === EddStatus::Expired->value;
        })->count();

        return [
            'active' => $active,
            'due_30_days' => $due30Days,
            'expired' => $expired,
        ];
    }

    /**
     * Get findings count by severity for the last 7 days.
     */
    protected function getFindingsBySeverityLast7Days(): array
    {
        $sevenDaysAgo = now()->subDays(7);

        $findings = ComplianceFinding::query()
            ->where('generated_at', '>=', $sevenDaysAgo)
            ->whereNotIn('status', ['Dismissed', 'CaseCreated'])
            ->select('severity', DB::raw('COUNT(*) as count'))
            ->groupBy('severity')
            ->get();

        // Map severity values to labels
        $result = [];
        foreach ($findings as $finding) {
            $severityValue = $finding->severity instanceof FindingSeverity
                ? $finding->severity->value
                : $finding->severity;
            $label = $this->mapSeverityToLabel($severityValue);
            $result[$label] = $finding->count;
        }

        return $result;
    }

    /**
     * Map severity value to label.
     */
    protected function mapSeverityToLabel(string $severity): string
    {
        return match ($severity) {
            'Low' => FindingSeverity::Low->label(),
            'Medium' => FindingSeverity::Medium->label(),
            'High' => FindingSeverity::High->label(),
            'Critical' => FindingSeverity::Critical->label(),
            default => $severity,
        };
    }

    /**
     * Get risk distribution across customer portfolio.
     */
    protected function getRiskDistribution(): array
    {
        $profiles = CustomerRiskProfile::query()
            ->select('risk_tier', DB::raw('COUNT(*) as count'))
            ->groupBy('risk_tier')
            ->get()
            ->pluck('count', 'risk_tier')
            ->toArray();

        return [
            'Low' => $profiles['Low'] ?? 0,
            'Medium' => $profiles['Medium'] ?? 0,
            'High' => $profiles['High'] ?? 0,
            'Critical' => $profiles['Critical'] ?? 0,
        ];
    }

    /**
     * Get case aging and SLA metrics.
     */
    public function getCaseAging(): array
    {
        $openCases = ComplianceCase::query()
            ->where('status', '!=', ComplianceCaseStatus::Closed->value)
            ->orderBy('created_at', 'asc')
            ->get();

        $closedCases = ComplianceCase::query()
            ->where('status', '=', ComplianceCaseStatus::Closed->value)
            ->whereNotNull('resolved_at')
            ->get();

        // Calculate average resolution time for closed cases
        $avgResolutionTimeHours = 0;
        if ($closedCases->isNotEmpty()) {
            $totalHours = 0;
            foreach ($closedCases as $case) {
                $created = $case->created_at instanceof Carbon ? $case->created_at : Carbon::parse($case->created_at);
                $resolved = $case->resolved_at instanceof Carbon ? $case->resolved_at : Carbon::parse($case->resolved_at);
                $totalHours += $created->diffInHours($resolved);
            }
            $avgResolutionTimeHours = (int) round($totalHours / $closedCases->count());
        }

        // Find cases breaching SLA
        $casesBreachingSla = ComplianceCase::query()
            ->where('sla_deadline', '<', now())
            ->where('status', '!=', ComplianceCaseStatus::Closed->value)
            ->count();

        // Find oldest open case
        $oldestOpenCase = $openCases->first();

        return [
            'avg_resolution_time_hours' => $avgResolutionTimeHours,
            'cases_breaching_sla' => $casesBreachingSla,
            'oldest_open_case' => $oldestOpenCase ? [
                'id' => $oldestOpenCase->id,
                'case_number' => $oldestOpenCase->case_number,
                'status' => $oldestOpenCase->status->value,
                'created_at' => $oldestOpenCase->created_at->toIso8601String(),
                'sla_deadline' => $oldestOpenCase->sla_deadline?->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * Get BNM regulatory calendar with upcoming filing deadlines.
     */
    public function getBnmCalendar(): array
    {
        $upcoming = [];

        // LCTR - Monthly (submit within 7 working days of month end)
        $upcoming[] = $this->calculateMonthlyDeadline('LCTR', now()->startOfMonth()->subMonth(), self::MONTHLY_FILING_DEADLINE_DAYS);

        // LMCA - Monthly (submit within 7 working days of month end)
        $upcoming[] = $this->calculateMonthlyDeadline('LMCA', now()->startOfMonth()->subMonth(), self::MONTHLY_FILING_DEADLINE_DAYS);

        // QLVR - Quarterly (submit within 10 working days of quarter end)
        $upcoming[] = $this->calculateQuarterlyDeadline(now(), self::QUARTERLY_FILING_DEADLINE_DAYS);

        // Current month LCTR/LMCA deadline
        $upcoming[] = $this->calculateMonthlyDeadline('LCTR', now()->startOfMonth(), self::MONTHLY_FILING_DEADLINE_DAYS);
        $upcoming[] = $this->calculateMonthlyDeadline('LMCA', now()->startOfMonth(), self::MONTHLY_FILING_DEADLINE_DAYS);

        // Sort by deadline
        usort($upcoming, function ($a, $b) {
            return strcmp($a['deadline'], $b['deadline']);
        });

        return [
            'upcoming' => $upcoming,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Calculate monthly filing deadline.
     */
    protected function calculateMonthlyDeadline(string $type, Carbon $monthStart, int $workingDays): array
    {
        $monthEnd = $monthStart->copy()->endOfMonth();
        $deadline = $this->addWorkingDays($monthEnd, $workingDays);

        return [
            'type' => $type,
            'period' => $monthStart->format('Y-m'),
            'period_start' => $monthStart->toDateString(),
            'period_end' => $monthEnd->toDateString(),
            'deadline' => $deadline->toDateString(),
            'working_days_deadline' => $workingDays,
            'is_upcoming' => $deadline->isFuture(),
            'is_overdue' => $deadline->isPast() && $deadline->toDateString() !== now()->toDateString(),
        ];
    }

    /**
     * Calculate quarterly filing deadline.
     */
    protected function calculateQuarterlyDeadline(Carbon $date, int $workingDays): array
    {
        $quarter = (int) ceil((int) $date->format('n') / 3);
        $year = (int) $date->format('Y');
        $quarterStart = Carbon::create($year, (($quarter - 1) * 3) + 1, 1)->startOfMonth();
        $quarterEnd = $quarterStart->copy()->addMonths(3)->subDay();
        $deadline = $this->addWorkingDays($quarterEnd, $workingDays);

        return [
            'type' => 'QLVR',
            'period' => "{$year}-Q{$quarter}",
            'period_start' => $quarterStart->toDateString(),
            'period_end' => $quarterEnd->toDateString(),
            'deadline' => $deadline->toDateString(),
            'working_days_deadline' => $workingDays,
            'is_upcoming' => $deadline->isFuture(),
            'is_overdue' => $deadline->isPast() && $deadline->toDateString() !== now()->toDateString(),
        ];
    }

    /**
     * Add working days to a date (excluding weekends).
     */
    protected function addWorkingDays(Carbon $date, int $days): Carbon
    {
        $result = $date->copy();
        $added = 0;

        while ($added < $days) {
            $result->addDay();
            // Skip weekends (Saturday = 6, Sunday = 7)
            if (! in_array($result->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY])) {
                $added++;
            }
        }

        return $result;
    }

    /**
     * Get audit trail for compliance actions.
     */
    public function getAuditTrail(array $filters = []): array
    {
        $perPage = $filters['per_page'] ?? 15;
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;
        $caseId = $filters['case_id'] ?? null;

        $query = SystemLog::query()
            ->where('entity_type', 'like', '%Compliance%')
            ->orWhere('action', 'like', '%compliance%')
            ->orWhere('action', 'like', '%case%')
            ->orWhere('action', 'like', '%edd%')
            ->orWhere('action', 'like', '%str%')
            ->orderBy('created_at', 'desc');

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        if ($caseId) {
            $query->where('entity_id', $caseId);
        }

        $results = $query->paginate($perPage);

        return [
            'data' => $results->items(),
            'current_page' => $results->currentPage(),
            'per_page' => $results->perPage(),
            'total' => $results->total(),
            'last_page' => $results->lastPage(),
        ];
    }

    /**
     * Get auto-generated reports.
     */
    public function getAutoGeneratedReports(): array
    {
        $reports = ReportGenerated::query()
            ->whereIn('report_type', ['MSB2', 'LCTR', 'LMCA', 'QLVR'])
            ->orderBy('generated_at', 'desc')
            ->limit(20)
            ->get();

        $pendingCount = $reports->where('status', 'Pending')->count();

        return [
            'pending_count' => $pendingCount,
            'pending_reports' => $reports->where('status', 'Pending')->values()->toArray(),
            'recent_reports' => $reports->take(10)->values()->toArray(),
        ];
    }

    /**
     * Export audit trail to CSV format.
     */
    public function exportAuditTrailToCsv(array $filters = []): string
    {
        $trail = $this->getAuditTrail(array_merge($filters, ['per_page' => 10000]));
        $data = $trail['data'];

        $csv = "ID,Action,Description,Entity Type,Entity ID,User ID,IP Address,Created At\n";

        foreach ($data as $entry) {
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%s,%s,%s\n",
                $entry->id ?? 0,
                $this->escapeCsv($entry->action ?? ''),
                $this->escapeCsv($entry->description ?? ''),
                $this->escapeCsv($entry->entity_type ?? ''),
                $entry->entity_id ?? '',
                $entry->user_id ?? '',
                $entry->ip_address ?? '',
                $entry->created_at ?? ''
            );
        }

        return $csv;
    }

    /**
     * Escape CSV field value.
     */
    protected function escapeCsv(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
