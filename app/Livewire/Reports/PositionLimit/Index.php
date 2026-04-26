<?php

namespace App\Livewire\Reports\PositionLimit;

use App\Livewire\BaseComponent;
use App\Services\ReportingService;
use Illuminate\View\View;

class Index extends BaseComponent
{
    public ?array $reportData = null;

    public function mount(): void
    {
        $this->loadReport();
    }

    public function loadReport(): void
    {
        $reportingService = app(ReportingService::class);
        $this->reportData = $reportingService->generatePositionLimitReport();
    }

    public function render(): View
    {
        return view('livewire.reports.position-limit.index', [
            'reportData' => $this->reportData,
        ]);
    }
}
