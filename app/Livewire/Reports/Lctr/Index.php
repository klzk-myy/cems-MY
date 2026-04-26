<?php

namespace App\Livewire\Reports\Lctr;

use App\Livewire\BaseComponent;
use App\Services\ReportingService;
use Illuminate\View\View;

class Index extends BaseComponent
{
    public ?string $selectedMonth = null;

    public ?array $transactions = null;

    public function mount(): void
    {
        $this->selectedMonth = request('month', now()->subMonth()->format('Y-m'));
        $this->loadReport();
    }

    public function loadReport(): void
    {
        if (! $this->selectedMonth) {
            $this->transactions = null;

            return;
        }

        $reportingService = app(ReportingService::class);
        $result = $reportingService->generateLCTRData($this->selectedMonth);

        $this->transactions = $result;
    }

    public function render(): View
    {
        return view('livewire.reports.lctr.index', [
            'month' => $this->selectedMonth,
            'transactions' => $this->transactions,
        ]);
    }
}
