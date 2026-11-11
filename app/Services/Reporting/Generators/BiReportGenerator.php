<?php

namespace App\Services\Reporting\Generators;

use App\Enums\ReportType;
use App\Services\Bi\KpiService;
use App\Services\Reporting\CsvReportWriter;
use Carbon\Carbon;

/**
 * Generates the BI export CSVs consumed by the scheduled-report pipeline
 * and the /bi export buttons. All data comes from the analytics rollups
 * via KpiService so scheduled exports never scan the OLTP tables
 * (except the documented distinct-customer query).
 */
class BiReportGenerator
{
    public function __construct(
        protected KpiService $kpiService,
        protected CsvReportWriter $csvReportWriter,
    ) {}

    /**
     * Single entry point for the scheduling pipeline and manual exports.
     *
     * @param  array<string, mixed>  $params  branch_id (nullable) is the only supported filter
     */
    public function generate(ReportType $type, Carbon $from, Carbon $to, array $params = []): string
    {
        $branchId = isset($params['branch_id']) && $params['branch_id'] !== ''
            ? (int) $params['branch_id']
            : null;

        return match ($type) {
            ReportType::BiDailyDigest => $this->digest($from, $to, $branchId),
            ReportType::BiBranchPerformance => $this->branchReport($from, $to, $branchId),
            ReportType::BiCurrencyBreakdown => $this->currencyReport($from, $to, $branchId),
            ReportType::BiTellerProductivity => $this->tellerReport($from, $to, $branchId),
            ReportType::BiTopCustomers => $this->customerReport($from, $to, $branchId),
            default => throw new \InvalidArgumentException("Unsupported BI report type: {$type->value}"),
        };
    }

    /**
     * Executive digest for a period (a single day for scheduled runs, an
     * arbitrary range for dashboard exports): the periodMetrics map as
     * key/value rows plus the period's currency breakdown.
     */
    public function digest(Carbon $from, Carbon $to, ?int $branchId): string
    {
        $metrics = $this->kpiService->periodMetrics($from, $to, $branchId);
        $suffix = $from->isSameDay($to)
            ? $from->toDateString()
            : $from->toDateString().'_'.$to->toDateString();

        $rows = [
            ['Metric', 'Value'],
            ...collect($metrics)->map(fn ($v, $k) => [(string) $k, (string) $v])->values()->all(),
            [],
            ['Currency', 'Buy Qty', 'Sell Qty', 'Buy MYR', 'Sell MYR', 'Volume MYR', 'Margin MYR', 'Share %'],
            ...collect($this->kpiService->currencyBreakdown($from, $to, $branchId))->map(fn (array $c) => [
                $c['currency_code'], $c['buy_qty'], $c['sell_qty'], $c['buy_volume_myr'],
                $c['sell_volume_myr'], $c['volume_myr'], $c['realized_margin_myr'], $c['share_pct'],
            ])->all(),
        ];

        return $this->csvReportWriter->write(
            "BI_Digest_{$suffix}.csv",
            ["BI Digest — {$suffix}".($branchId ? " (branch {$branchId})" : ' (all branches)')],
            $rows
        );
    }

    /**
     * Branch comparison table — the /bi/branches export. A non-null
     * $branchId scopes the export to that branch's row, matching the
     * page's filtering for branch-pinned viewers.
     */
    public function branchReport(Carbon $from, Carbon $to, ?int $branchId = null): string
    {
        $rows = collect($this->kpiService->branchComparison($from, $to))
            ->filter(fn (array $b) => $branchId === null || (int) ($b['branch_id'] ?? 0) === $branchId)
            ->map(fn (array $b) => [
                $b['branch_name'], $b['tx_count'], $b['completed_count'], $b['cancelled_count'],
                $b['volume_myr'], $b['realized_margin_myr'], $b['margin_pct'],
                $b['avg_ticket_myr'], $b['unique_customers'],
            ])
            ->values()
            ->all();

        return $this->csvReportWriter->writeWithTitleRows(
            "BI_Branches_{$from->format('Y-m-d')}_{$to->format('Y-m-d')}.csv",
            [['BI Branch Performance'], ["{$from->toDateString()} to {$to->toDateString()}"]],
            ['Branch', 'Transactions', 'Completed', 'Cancelled', 'Volume MYR', 'Margin MYR', 'Margin %', 'Avg Ticket MYR', 'Unique Customers'],
            $rows
        );
    }

    public function currencyReport(Carbon $from, Carbon $to, ?int $branchId): string
    {
        $rows = collect($this->kpiService->currencyBreakdown($from, $to, $branchId))->map(fn (array $c) => [
            $c['currency_code'], $c['buy_qty'], $c['sell_qty'], $c['buy_volume_myr'],
            $c['sell_volume_myr'], $c['volume_myr'], $c['realized_margin_myr'], $c['share_pct'],
        ])->all();

        return $this->csvReportWriter->writeWithTitleRows(
            "BI_Currencies_{$from->format('Y-m-d')}_{$to->format('Y-m-d')}.csv",
            [['BI Currency Breakdown'], ["{$from->toDateString()} to {$to->toDateString()}".($branchId ? " — branch {$branchId}" : '')]],
            ['Currency', 'Buy Qty', 'Sell Qty', 'Buy MYR', 'Sell MYR', 'Volume MYR', 'Margin MYR', 'Share %'],
            $rows
        );
    }

    public function tellerReport(Carbon $from, Carbon $to, ?int $branchId): string
    {
        $rows = collect($this->kpiService->tellerProductivity($from, $to, $branchId))->map(fn (array $t) => [
            $t['teller_name'], $t['tx_count'], $t['volume_myr'], $t['cancelled_count'],
            $t['rejected_count'], $t['rate_override_count'], $t['avg_approval_seconds'],
        ])->all();

        return $this->csvReportWriter->writeWithTitleRows(
            "BI_Tellers_{$from->format('Y-m-d')}_{$to->format('Y-m-d')}.csv",
            [['BI Teller Productivity'], ["{$from->toDateString()} to {$to->toDateString()}".($branchId ? " — branch {$branchId}" : '')]],
            ['Teller', 'Transactions', 'Volume MYR', 'Cancelled', 'Rejected', 'Rate Overrides', 'Avg Approval (s)'],
            $rows
        );
    }

    public function customerReport(Carbon $from, Carbon $to, ?int $branchId): string
    {
        $rows = collect($this->kpiService->topCustomers($from, $to, $branchId, 500))->map(fn (array $c) => [
            $c['customer_id'], $c['full_name'], $c['tx_count'], $c['volume_myr'],
            $c['realized_margin_myr'], $c['currencies_used'],
        ])->all();

        return $this->csvReportWriter->writeWithTitleRows(
            "BI_Customers_{$from->format('Y-m-d')}_{$to->format('Y-m-d')}.csv",
            [['BI Top Customers'], ["{$from->toDateString()} to {$to->toDateString()}".($branchId ? " — branch {$branchId}" : '')]],
            ['Customer ID', 'Name', 'Transactions', 'Volume MYR', 'Margin MYR', 'Currencies Used'],
            $rows
        );
    }
}
