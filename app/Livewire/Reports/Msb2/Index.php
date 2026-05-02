<?php

namespace App\Livewire\Reports\Msb2;

use App\Livewire\BaseComponent;
use App\Services\ReportingService;
use Illuminate\View\View;

class Index extends BaseComponent
{
    public ?string $selectedDate = null;

    public ?array $report = null;

    public function mount(): void
    {
        $this->selectedDate = request('date', now()->subDay()->toDateString());
        $this->loadReport();
    }

    public function updatedSelectedDate(): void
    {
        $this->loadReport();
    }

    public function loadReport(): void
    {
        if (! $this->selectedDate) {
            $this->report = null;

            return;
        }

        $reportingService = app(ReportingService::class);
        $result = $reportingService->generateMSB2Data($this->selectedDate);

        $this->report = [
            'date' => $this->selectedDate,
            'total_transactions' => $result['totals']['total_transactions'] ?? 0,
            'buy_volume' => $result['totals']['buy_volume'] ?? '0.00',
            'sell_volume' => $result['totals']['sell_volume'] ?? '0.00',
            'currencies' => $result['data'] ?? [],
        ];
    }

    public function render(): View
    {
        return view('livewire.reports.msb2.index', [
            'report' => $this->report,
        ]);
    }
}
