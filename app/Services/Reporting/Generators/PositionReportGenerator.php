<?php

namespace App\Services\Reporting\Generators;

use App\Models\CurrencyPosition;
use App\Services\Reporting\CsvReportWriter;
use App\Services\System\MathService;
use App\Services\ThresholdService;

/**
 * Position-based reports: currency position, unrealized P&L, and position
 * limit utilization.
 */
class PositionReportGenerator
{
    public function __construct(
        protected MathService $mathService,
        protected ThresholdService $thresholdService,
        protected CsvReportWriter $csvReportWriter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generateCurrencyPositionReport(): array
    {
        $positions = CurrencyPosition::with('currency')->get();

        $data = [];
        $totalUnrealizedPnl = '0';

        foreach ($positions as $position) {
            $data[] = [
                'currency_code' => $position->currency_code,
                'currency_name' => $position->currency->name ?? $position->currency_code,
                'quantity' => $position->quantity,
                'average_cost' => $position->average_cost,
                'current_rate' => $position->current_rate,
                'unrealized_gain_loss' => $position->unrealized_gain_loss,
            ];
            $totalUnrealizedPnl = $this->mathService->add($totalUnrealizedPnl, $position->unrealized_gain_loss ?? '0');
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'positions' => $data,
            'total_unrealized_pnl' => $totalUnrealizedPnl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function generateUnrealizedPnLReport(): array
    {
        $positions = CurrencyPosition::with('currency')
            ->whereRaw('unrealized_gain_loss != 0')
            ->get();

        $data = [];
        $totalGain = '0';
        $totalLoss = '0';

        foreach ($positions as $position) {
            $pnl = $position->unrealized_gain_loss ?? '0';

            if ($this->mathService->compare($pnl, '0') >= 0) {
                $totalGain = $this->mathService->add($totalGain, $pnl);
            } else {
                $totalLoss = $this->mathService->add($totalLoss, $pnl);
            }

            $data[] = [
                'currency_code' => $position->currency_code,
                'currency_name' => $position->currency->name ?? $position->currency_code,
                'quantity' => $position->quantity,
                'average_cost' => $position->average_cost,
                'current_rate' => $position->current_rate,
                'unrealized_gain_loss' => $pnl,
                'is_gain' => $this->mathService->compare($pnl, '0') >= 0,
            ];
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'positions' => $data,
            'total_gain' => $totalGain,
            'total_loss' => $totalLoss,
            'net_pnl' => $this->mathService->add($totalGain, $totalLoss),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function generatePositionLimitReport(): array
    {
        $positions = CurrencyPosition::with('currency')->get();
        $limits = $this->thresholdService->getPositionLimits();

        $data = [];
        $totalExposure = '0';

        foreach ($positions as $position) {
            $limit = $limits[$position->currency_code] ?? null;
            $currentBalance = $position->quantity;
            if ($this->mathService->compare($currentBalance, '0') < 0) {
                $currentBalance = $this->mathService->multiply($currentBalance, '-1');
            }
            $limitValue = $limit ?? '0';
            $utilization = $this->mathService->compare($limitValue, '0') > 0
                ? $this->mathService->multiply(
                    $this->mathService->divide($currentBalance, $limitValue),
                    '100'
                )
                : '0';

            $data[] = [
                'currency_code' => $position->currency_code,
                'currency_name' => $position->currency->name ?? $position->currency_code,
                'current_balance' => $position->quantity,
                'position_limit' => $limit,
                'utilization_percent' => $utilization,
                'average_cost' => $position->average_cost,
                'current_rate' => $position->current_rate,
                'exposure_myr' => $this->mathService->multiply($currentBalance, $position->current_rate ?? '0'),
                'status' => $this->mathService->compare($utilization, '90') >= 0
                    ? 'Critical'
                    : ($this->mathService->compare($utilization, '75') >= 0 ? 'Warning' : 'Normal'),
            ];

            $totalExposure = $this->mathService->add(
                $totalExposure,
                $this->mathService->multiply($currentBalance, $position->current_rate ?? '0')
            );
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'total_exposure_myr' => $totalExposure,
            'positions' => $data,
            'summary' => [
                'total_currencies' => count($data),
                'currencies_at_warning' => collect($data)->where('status', 'Warning')->count(),
                'currencies_at_critical' => collect($data)->where('status', 'Critical')->count(),
            ],
        ];
    }

    public function generatePositionLimitCsv(): string
    {
        $data = $this->generatePositionLimitReport();
        $filename = 'PositionLimit_'.now()->format('Y-m-d').'.csv';

        $titleRows = [
            ['BNM Position Limit Utilization Report'],
            ['Generated', $data['generated_at']],
            ['Total Exposure (MYR)', $data['total_exposure_myr']],
        ];

        $headers = [
            'Currency',
            'Current Balance',
            'Position Limit',
            'Utilization %',
            'Avg Cost Rate',
            'Last Valuation Rate',
            'Exposure (MYR)',
            'Status',
        ];

        $rows = [];
        foreach ($data['positions'] as $row) {
            $rows[] = [
                $row['currency_code'],
                $row['current_balance'],
                $row['position_limit'] ?? 'N/A',
                $row['utilization_percent'].'%',
                $row['average_cost'],
                $row['current_rate'],
                $row['exposure_myr'],
                $row['status'],
            ];
        }

        return $this->csvReportWriter->writeWithTitleRows($filename, $titleRows, $headers, $rows);
    }
}
