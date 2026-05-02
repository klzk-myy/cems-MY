<?php

namespace App\Livewire\Reports\Analytics;

use App\Enums\ComplianceFlagType;
use App\Livewire\BaseComponent;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;
use App\Services\ThresholdService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ComplianceSummary extends BaseComponent
{
    public string $startDate;

    public string $endDate;

    public array $flaggedStats = [];

    public int $largeTransactions = 0;

    public int $eddCount = 0;

    public int $suspiciousCount = 0;

    public function mount(): void
    {
        $this->startDate = now()->subMonth()->toDateString();
        $this->endDate = now()->toDateString();
        $this->loadData();
    }

    protected function loadData(): void
    {
        $this->loadFlaggedStats();
        $this->loadLargeTransactions();
        $this->loadEddCount();
        $this->loadSuspiciousCount();
    }

    protected function loadFlaggedStats(): void
    {
        $flagged = FlaggedTransaction::whereBetween('created_at', [$this->startDate, $this->endDate])
            ->select('flag_type', DB::raw('COUNT(*) as count'))
            ->groupBy('flag_type')
            ->get();

        $this->flaggedStats = $flagged->map(function ($row) {
            return [
                'flag_type' => $row->flag_type ?? 'Unknown',
                'count' => (int) $row->count,
            ];
        })->toArray();
    }

    protected function loadLargeTransactions(): void
    {
        $thresholdService = app(ThresholdService::class);

        $this->largeTransactions = Transaction::where('amount_local', '>=', $thresholdService->getLctrThreshold())
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->count();
    }

    protected function loadEddCount(): void
    {
        $this->eddCount = Transaction::where('cdd_level', 'enhanced')
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->count();
    }

    protected function loadSuspiciousCount(): void
    {
        $suspiciousTypes = [
            ComplianceFlagType::Structuring->value,
            ComplianceFlagType::SanctionMatch->value,
        ];

        $this->suspiciousCount = FlaggedTransaction::whereIn('flag_type', $suspiciousTypes)
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->count();
    }

    public function updatedStartDate(): void
    {
        $this->loadData();
    }

    public function updatedEndDate(): void
    {
        $this->loadData();
    }

    public function render(): View
    {
        return view('livewire.reports.analytics.compliance-summary');
    }
}
