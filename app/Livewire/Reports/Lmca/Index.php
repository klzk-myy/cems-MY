<?php

namespace App\Livewire\Reports\Lmca;

use App\Livewire\BaseComponent;
use App\Services\ReportingService;
use Illuminate\View\View;

class Index extends BaseComponent
{
    public ?string $selectedMonth = null;

    public ?array $reportData = null;

    public function mount(): void
    {
        $this->selectedMonth = request('month', now()->subMonth()->format('Y-m'));
        $this->loadReport();
    }

    public function loadReport(): void
    {
        if (! $this->selectedMonth) {
            $this->reportData = null;

            return;
        }

        $reportingService = app(ReportingService::class);
        $this->reportData = $reportingService->generateFormLMCA($this->selectedMonth);
    }

    public function render(): View
    {
        return view('livewire.reports.lmca.index', [
            'month' => $this->selectedMonth,
            'reportData' => $this->reportData,
        ]);
    }
}
