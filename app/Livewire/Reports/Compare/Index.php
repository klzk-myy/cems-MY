<?php

namespace App\Livewire\Reports\Compare;

use App\Livewire\BaseComponent;
use App\Models\ReportGenerated;
use Illuminate\View\View;

class Index extends BaseComponent
{
    public ?string $reportType = null;

    public ?string $periodStart = null;

    public ?int $version1 = null;

    public ?int $version2 = null;

    public ?ReportGenerated $report1 = null;

    public ?ReportGenerated $report2 = null;

    public array $reportTypes = [];

    public string $error = '';

    public function mount(): void
    {
        $this->loadReportTypes();
    }

    protected function loadReportTypes(): void
    {
        $this->reportTypes = ReportGenerated::select('report_type')
            ->distinct()
            ->pluck('report_type')
            ->toArray();
    }

    public function compare(): void
    {
        $this->error = '';

        if (! $this->reportType || ! $this->periodStart || ! $this->version1 || ! $this->version2) {
            $this->error = 'Please fill in all required fields';

            return;
        }

        $this->report1 = ReportGenerated::where('report_type', $this->reportType)
            ->where('period_start', $this->periodStart)
            ->where('version', $this->version1)
            ->first();

        $this->report2 = ReportGenerated::where('report_type', $this->reportType)
            ->where('period_start', $this->periodStart)
            ->where('version', $this->version2)
            ->first();

        if (! $this->report1 || ! $this->report2) {
            $this->error = 'Report version not found';

            return;
        }
    }

    public function render(): View
    {
        return view('livewire.reports.compare.index');
    }
}
