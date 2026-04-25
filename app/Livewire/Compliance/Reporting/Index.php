<?php

namespace App\Livewire\Compliance\Reporting;

use App\Livewire\BaseComponent;
use App\Models\ReportGenerated;
use App\Models\ReportSchedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

class Index extends BaseComponent
{
    protected function getReports(): Collection
    {
        return ReportGenerated::orderByDesc('generated_at')
            ->limit(50)
            ->get();
    }

    protected function getSchedules(): Collection
    {
        return ReportSchedule::active()
            ->orderBy('next_run_at')
            ->get();
    }

    protected function getSummary(): array
    {
        return [
            'total' => ReportGenerated::count(),
            'ctos' => ReportGenerated::byType('ctos')->count(),
            'str' => ReportGenerated::byType('str')->count(),
            'lctr' => ReportGenerated::byType('lctr')->count(),
            'active_schedules' => ReportSchedule::active()->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.compliance.reporting.index', [
            'reports' => $this->getReports(),
            'schedules' => $this->getSchedules(),
            'summary' => $this->getSummary(),
            'reportTypes' => ReportSchedule::getReportTypes(),
        ]);
    }
}
