<?php

namespace App\Services\Accounting;

use App\Enums\ReportType;
use App\Exceptions\Domain\MonthEndPreCheckFailedException;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\ReportGenerated;
use App\Models\RevaluationEntry;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Reporting\ReportingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonthEndCloseService
{
    public function __construct(
        protected RevaluationService $revaluationService,
        protected AccountingService $accountingService,
        protected AuditService $auditService,
        protected ReportingService $reportingService,
    ) {}

    public function runMonthEndClosing(Carbon $date, User $initiator): array
    {
        $checkResult = $this->preFlightChecks($date);
        if (! $checkResult['passed']) {
            throw new MonthEndPreCheckFailedException($checkResult['failures']);
        }

        return DB::transaction(function () use ($date, $initiator) {
            $results = [];

            $results['revaluation'] = $this->revaluationService->runRevaluationWithJournal(
                $date->toDateString(),
                $initiator->id
            );

            $results['reports'] = $this->generateReports($date);

            $results['period'] = $this->closePeriod($date);

            $this->auditService->log(
                'month_end_close',
                $initiator->id,
                'AccountingPeriod',
                $results['period']['period_id'] ?? null,
                [],
                [
                    'date' => $date->toDateString(),
                    'revaluation' => $results['revaluation'],
                    'reports' => $results['reports'],
                    'period_closed' => $results['period'],
                ]
            );

            return $results;
        });
    }

    public function preFlightChecks(Carbon $date): array
    {
        $failures = [];

        $period = AccountingPeriod::forDate($date->toDateString())->first();
        if (! $period) {
            $failures[] = 'No accounting period found for '.$date->toDateString();
        } elseif ($period->isClosed()) {
            $failures[] = 'Period '.$period->period_code.' is already closed';
        }

        $pendingEntries = JournalEntry::whereHas('period', function ($q) use ($date) {
            $q->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date);
        })
            ->where('status', '!=', 'Posted')
            ->count();

        if ($pendingEntries > 0) {
            $failures[] = $pendingEntries.' pending journal entries must be posted first';
        }

        if ($period) {
            $openPeriods = AccountingPeriod::forDate($date->toDateString())
                ->where('status', 'Open')
                ->where('id', '!=', $period->id)
                ->count();

            if ($openPeriods > 0) {
                $failures[] = $openPeriods.' other open periods found for this date';
            }
        }

        return [
            'passed' => empty($failures),
            'failures' => $failures,
        ];
    }

    public function generateReports(Carbon $date): array
    {
        $reports = [];
        $month = $date->format('Y-m');

        try {
            $lmcaPath = $this->reportingService->generateFormLMCACsv($month);
            $reports['lmca'] = ['status' => 'success', 'path' => $lmcaPath];
        } catch (\Exception $e) {
            Log::error('LMCA report generation failed', ['error' => $e->getMessage()]);
            $reports['lmca'] = ['status' => 'failed', 'error' => $e->getMessage()];
        }

        $allSuccessful = collect($reports)->every(fn ($report) => ($report['status'] ?? 'failed') === 'success');

        ReportGenerated::create([
            'report_type' => ReportType::MonthEnd,
            'period_start' => $date->copy()->startOfMonth(),
            'period_end' => $date->copy()->endOfMonth(),
            'generated_by' => auth()->id() ?? 1,
            'generated_at' => now(),
            'file_format' => 'CSV',
            'status' => $allSuccessful ? 'Generated' : 'Failed',
        ]);

        return $reports;
    }

    public function closePeriod(Carbon $date): array
    {
        return DB::transaction(function () use ($date) {
            $period = AccountingPeriod::forDate($date->toDateString())->first();

            if (! $period) {
                throw new \InvalidArgumentException('No period found for date');
            }

            $period->update([
                'status' => 'Closed',
                'closed_at' => now(),
                'closed_by' => auth()->id(),
            ]);

            $nextMonth = $date->copy()->addMonth();
            $existingNext = AccountingPeriod::forDate($nextMonth->toDateString())->first();

            if (! $existingNext) {
                AccountingPeriod::create([
                    'period_code' => $nextMonth->format('Y-m'),
                    'start_date' => $nextMonth->startOfMonth()->toDateString(),
                    'end_date' => $nextMonth->endOfMonth()->toDateString(),
                    'period_type' => 'month',
                    'status' => 'Open',
                    'fiscal_year_id' => $period->fiscal_year_id ?? null,
                ]);
            }

            return [
                'period_id' => $period->id,
                'period_code' => $period->period_code,
                'closed_at' => $period->closed_at->toDateTimeString(),
            ];
        });
    }

    public function getMonthEndStatus(Carbon $date): array
    {
        $period = AccountingPeriod::forDate($date->toDateString())->first();

        $revaluationEntries = RevaluationEntry::whereBetween('revaluation_date', [
            $date->startOfMonth()->toDateString(),
            $date->endOfMonth()->toDateString(),
        ])->count();

        $reportGenerated = ReportGenerated::where('report_type', ReportType::MonthEnd)
            ->whereBetween('period_start', [$date->startOfMonth(), $date->endOfMonth()])
            ->whereBetween('period_end', [$date->startOfMonth(), $date->endOfMonth()])
            ->exists();

        return [
            'date' => $date->toDateString(),
            'has_period' => $period !== null,
            'period_status' => $period?->status,
            'period_code' => $period?->period_code,
            'revaluation_run' => $revaluationEntries > 0,
            'revaluation_entries' => $revaluationEntries,
            'reports_generated' => $reportGenerated,
            'pre_check' => $this->preFlightChecks($date),
        ];
    }
}
