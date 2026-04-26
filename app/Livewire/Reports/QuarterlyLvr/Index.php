<?php

namespace App\Livewire\Reports\QuarterlyLvr;

use App\Livewire\BaseComponent;
use App\Services\ReportingService;
use Illuminate\View\View;

class Index extends BaseComponent
{
    public ?string $selectedQuarter = null;

    public ?array $reportData = null;

    public function mount(): void
    {
        $this->selectedQuarter = request('quarter', $this->getPreviousQuarter());
        $this->loadReport();
    }

    public function loadReport(): void
    {
        if (! $this->selectedQuarter) {
            $this->reportData = null;

            return;
        }

        $reportingService = app(ReportingService::class);
        $this->reportData = $reportingService->generateQuarterlyLargeValueReport($this->selectedQuarter);
    }

    public function getPreviousQuarter(): string
    {
        $now = now();
        $q = ceil($now->format('n') / 3);
        $y = $now->year;

        if ($q === 1) {
            return ($y - 1).'-Q4';
        }

        return $y.'-Q'.($q - 1);
    }

    public function render(): View
    {
        return view('livewire.reports.quarterly-lvr.index', [
            'quarter' => $this->selectedQuarter,
            'reportData' => $this->reportData,
        ]);
    }
}
