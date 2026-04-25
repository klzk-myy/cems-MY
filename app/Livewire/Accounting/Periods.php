<?php

namespace App\Livewire\Accounting;

use App\Livewire\BaseComponent;
use App\Models\AccountingPeriod;
use App\Services\PeriodCloseService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class Periods extends BaseComponent
{
    public array $periods = [];

    public function mount(): void
    {
        $this->loadPeriods();
    }

    protected function loadPeriods(): void
    {
        $periodRecords = AccountingPeriod::orderBy('start_date', 'desc')
            ->limit(20)
            ->get();

        $this->periods = $periodRecords->map(function ($period) {
            return [
                'id' => $period->id,
                'period_code' => $period->period_code,
                'name' => $period->name ?? $period->period_code,
                'start_date' => $period->start_date?->format('d M Y') ?? 'N/A',
                'end_date' => $period->end_date?->format('d M Y') ?? 'N/A',
                'period_type' => $period->period_type ?? 'monthly',
                'is_closed' => $period->isClosed(),
                'status' => $period->status,
            ];
        })->toArray();
    }

    public function closePeriod(int $periodId): void
    {
        try {
            $period = AccountingPeriod::findOrFail($periodId);

            if ($period->isClosed()) {
                $this->error("Period {$period->period_code} is already closed.");

                return;
            }

            $periodCloseService = app(PeriodCloseService::class);
            $periodCloseService->closePeriod($period, auth()->id());

            $this->success("Period {$period->period_code} closed successfully.");
            $this->loadPeriods();

        } catch (\Exception $e) {
            Log::error('Period close failed', ['exception' => $e, 'period_id' => $periodId]);
            $this->error('Failed to close period: '.$e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.accounting.periods');
    }
}
