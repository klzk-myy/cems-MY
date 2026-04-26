<?php

namespace App\Livewire\Reports\History;

use App\Livewire\BaseComponent;
use App\Models\ReportGenerated;
use Illuminate\View\View;
use Livewire\WithPagination;

class Index extends BaseComponent
{
    use WithPagination;

    public ?string $reportType = null;

    public ?string $periodStart = null;

    public array $reportTypes = [];

    protected $reports;

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

    public function updatedReportType(): void
    {
        $this->resetPage();
    }

    public function updatedPeriodStart(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $query = ReportGenerated::with(['generatedBy', 'submittedBy'])
            ->orderBy('generated_at', 'desc');

        if ($this->reportType) {
            $query->where('report_type', $this->reportType);
        }

        if ($this->periodStart) {
            $query->where('period_start', $this->periodStart);
        }

        $reports = $query->paginate(20);

        return view('livewire.reports.history.index', compact('reports'));
    }
}
