<?php

namespace App\Services\Reporting;

use App\Enums\ReportGeneratedStatus;
use App\Enums\ReportType;
use App\Models\ReportGenerated;
use App\Services\Contracts\ReportingServiceInterface;
use App\Services\Reporting\Generators\LedgerBackedReportGenerator;
use App\Services\Reporting\Generators\LmcaReportGenerator;
use App\Services\Reporting\Generators\Msb2ReportGenerator;
use App\Services\Reporting\Generators\PositionReportGenerator;
use App\Services\Reporting\Generators\QlvrReportGenerator;
use App\Support\ActorContext;
use Carbon\Carbon;

/**
 * Reporting facade: keeps the public ReportingServiceInterface surface stable
 * while each report family lives in a dedicated generator under
 * Reporting\Generators.
 */
class ReportingService implements ReportingServiceInterface
{
    public function __construct(
        protected Msb2ReportGenerator $msb2Generator,
        protected LmcaReportGenerator $lmcaGenerator,
        protected QlvrReportGenerator $qlvrGenerator,
        protected PositionReportGenerator $positionGenerator,
        protected LedgerBackedReportGenerator $ledgerBackedGenerator,
    ) {}

    public function recordGeneratedReport(
        ReportType $reportType,
        Carbon $periodStart,
        Carbon $periodEnd,
        ReportGeneratedStatus $status = ReportGeneratedStatus::Generated,
        string $format = 'CSV'
    ): ReportGenerated {
        return ReportGenerated::create([
            'report_type' => $reportType,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'generated_by' => ActorContext::capture()->userId ?? config('cems.system_user_id', 1),
            'generated_at' => now(),
            'file_format' => $format,
            'status' => $status->value,
        ]);
    }

    public function generateMSB2(string $date): string
    {
        return $this->msb2Generator->generate($date);
    }

    public function generateMSB2Data(string $date): array
    {
        return $this->msb2Generator->generateData($date);
    }

    public function generateCurrencyPositionReport(): array
    {
        return $this->positionGenerator->generateCurrencyPositionReport();
    }

    public function generateUnrealizedPnLReport(): array
    {
        return $this->positionGenerator->generateUnrealizedPnLReport();
    }

    public function generateFormLMCA(string $month): array
    {
        return $this->lmcaGenerator->generate($month);
    }

    public function generateFormLMCACsv(string $month): string
    {
        return $this->lmcaGenerator->generateCsv($month);
    }

    public function generateQuarterlyLargeValueReport(string $quarter): array
    {
        return $this->qlvrGenerator->generate($quarter);
    }

    public function generateQuarterlyLargeValueCsv(string $quarter): string
    {
        return $this->qlvrGenerator->generateCsv($quarter);
    }

    public function generatePositionLimitReport(): array
    {
        return $this->positionGenerator->generatePositionLimitReport();
    }

    public function generatePositionLimitCsv(): string
    {
        return $this->positionGenerator->generatePositionLimitCsv();
    }

    public function generateReport(string $reportType, string $period): string
    {
        $type = ReportType::tryFrom($reportType);

        if ($type === null) {
            throw new \InvalidArgumentException("Unknown report type: {$reportType}");
        }

        return match ($type) {
            ReportType::Msb2 => $this->msb2Generator->generate($period),
            ReportType::Lmca => $this->lmcaGenerator->generateCsv(Carbon::parse($period)->format('Y-m')),
            ReportType::Qlvr => $this->qlvrGenerator->generateCsv($this->periodToQuarter($period)),
            ReportType::Plr => $this->positionGenerator->generatePositionLimitCsv(),
            ReportType::TrialBalance => $this->ledgerBackedGenerator->generateTrialBalance($period),
            ReportType::MonthEnd => $this->ledgerBackedGenerator->generateMonthEnd($period),
            ReportType::ProfitLoss => $this->ledgerBackedGenerator->generateProfitLoss($period),
            ReportType::BalanceSheet => $this->ledgerBackedGenerator->generateBalanceSheet($period),
        };
    }

    private function periodToQuarter(string $period): string
    {
        $date = Carbon::parse($period);

        return 'Q'.$date->quarter.'-'.$date->year;
    }
}
