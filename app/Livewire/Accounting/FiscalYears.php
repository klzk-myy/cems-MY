<?php

namespace App\Livewire\Accounting;

use App\Livewire\BaseComponent;
use App\Models\FiscalYear;
use App\Services\FiscalYearService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class FiscalYears extends BaseComponent
{
    public array $fiscalYears = [];

    public ?array $yearReport = null;

    public string $showCreateForm = 'no';

    public string $newYearCode = '';

    public string $newStartDate = '';

    public string $newEndDate = '';

    protected function loadFiscalYears(): void
    {
        $records = FiscalYear::orderBy('year_code', 'desc')->get();

        $this->fiscalYears = $records->map(function ($year) {
            return [
                'id' => $year->id,
                'year_code' => $year->year_code,
                'start_date' => $year->start_date?->format('d M Y') ?? 'N/A',
                'end_date' => $year->end_date?->format('d M Y') ?? 'N/A',
                'is_closed' => $year->isClosed(),
                'status' => $year->status,
            ];
        })->toArray();
    }

    public function mount(): void
    {
        $this->loadFiscalYears();
    }

    public function createFiscalYear(): void
    {
        $this->validate([
            'newYearCode' => 'required|string|max:10|unique:fiscal_years,year_code',
            'newStartDate' => 'required|date',
            'newEndDate' => 'required|date|after:newStartDate',
        ], [
            'newYearCode.unique' => 'A fiscal year with this code already exists.',
            'newEndDate.after' => 'End date must be after start date.',
        ]);

        try {
            $fiscalYearService = app(FiscalYearService::class);
            $year = $fiscalYearService->createFiscalYear(
                $this->newYearCode,
                $this->newStartDate,
                $this->newEndDate
            );

            $this->success("Fiscal year {$year->year_code} created successfully.");
            $this->reset(['newYearCode', 'newStartDate', 'newEndDate', 'showCreateForm']);
            $this->loadFiscalYears();

        } catch (\Exception $e) {
            Log::error('FiscalYear create failed', ['exception' => $e, 'year_code' => $this->newYearCode]);
            $this->error('Failed to create fiscal year: '.$e->getMessage());
        }
    }

    public function closeFiscalYear(string $yearCode, string $confirmCode): void
    {
        if ($confirmCode !== $yearCode) {
            $this->error('Year code confirmation failed.');

            return;
        }

        try {
            $fiscalYear = FiscalYear::where('year_code', $yearCode)->firstOrFail();
            $fiscalYearService = app(FiscalYearService::class);
            $result = $fiscalYearService->closeFiscalYear($fiscalYear);

            $this->success("Fiscal year {$yearCode} closed successfully. Net income: {$result['net_income']}");
            $this->loadFiscalYears();

        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
        } catch (\Exception $e) {
            Log::error('FiscalYear close failed', ['exception' => $e, 'year_code' => $yearCode]);
            $this->error('Failed to close fiscal year: '.$e->getMessage());
        }
    }

    public function viewReport(string $yearCode): void
    {
        try {
            $fiscalYearService = app(FiscalYearService::class);
            $this->yearReport = $fiscalYearService->getYearEndReport($yearCode);

        } catch (\Exception $e) {
            Log::error('FiscalYear report failed', ['exception' => $e, 'year_code' => $yearCode]);
            $this->error('Failed to generate report: '.$e->getMessage());
        }
    }

    public function closeReport(): void
    {
        $this->yearReport = null;
    }

    public function render(): View
    {
        return view('livewire.accounting.fiscal-years');
    }
}
