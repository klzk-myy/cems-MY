<?php

namespace App\Livewire\Rates;

use App\Livewire\BaseComponent;
use App\Models\Branch;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use App\Services\RateManagementService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class Index extends BaseComponent
{
    public array $rates = [];

    public array $availableDates = [];

    public ?Branch $currentBranch = null;

    public bool $canSelectBranch = false;

    public ?int $selectedBranchId = null;

    public array $branches = [];

    public function mount(): void
    {
        $user = Auth::user();
        $this->canSelectBranch = $user->role->isAdmin();

        if ($this->canSelectBranch) {
            $this->branches = Branch::all()->map(function ($b) {
                return ['id' => $b->id, 'code' => $b->code, 'name' => $b->name];
            })->toArray();
            $this->selectedBranchId = $user->branch_id;
        } else {
            $this->selectedBranchId = $user->branch_id;
        }

        $this->loadRates();
        $this->loadAvailableDates();
    }

    protected function loadRates(): void
    {
        $rateService = app(RateManagementService::class);
        $this->rates = $rateService->getRatesSummary($this->selectedBranchId);

        if ($this->selectedBranchId) {
            $this->currentBranch = Branch::find($this->selectedBranchId);
        }
    }

    protected function loadAvailableDates(): void
    {
        $query = ExchangeRateHistory::query();
        if ($this->selectedBranchId !== null) {
            $query->where('branch_id', $this->selectedBranchId);
        }
        $this->availableDates = $query->select('effective_date')
            ->distinct()
            ->orderBy('effective_date', 'desc')
            ->limit(30)
            ->get()
            ->pluck('effective_date')
            ->map(fn ($date) => $date->format('Y-m-d'))
            ->toArray();
    }

    public function fetchFromApi(): void
    {
        $user = Auth::user();

        if (! $user->role->isManager() && ! $user->role->isAdmin()) {
            $this->error('Only managers and admins can fetch rates from API');

            return;
        }

        try {
            $rateService = app(RateManagementService::class);
            $result = $rateService->fetchAndStoreRates($user, $this->selectedBranchId);

            if ($result['success']) {
                $this->success('Rates fetched successfully');
                $this->loadRates();
            } else {
                $this->error($result['message'] ?? 'Failed to fetch rates');
            }
        } catch (\Exception $e) {
            $this->error('Failed to fetch rates: '.$e->getMessage());
        }
    }

    public function copyPrevious(?string $date = null): void
    {
        $user = Auth::user();

        if (! $user->role->isManager() && ! $user->role->isAdmin()) {
            $this->error('Only managers and admins can copy previous rates');

            return;
        }

        $targetDate = $date ?? now()->subDay()->toDateString();

        $historyQuery = ExchangeRateHistory::where('effective_date', $targetDate);
        if ($this->selectedBranchId !== null) {
            $historyQuery->where('branch_id', $this->selectedBranchId);
        }
        $historicalRates = $historyQuery->get();

        if ($historicalRates->isEmpty()) {
            $this->error("No rates found for date {$targetDate}");

            return;
        }

        $copied = [];
        foreach ($historicalRates as $histRate) {
            $query = ExchangeRate::where('currency_code', $histRate->currency_code);
            if ($this->selectedBranchId !== null) {
                $query->forBranch($this->selectedBranchId);
            }
            $exchangeRate = $query->first();

            if ($exchangeRate) {
                $exchangeRate->update([
                    'rate_buy' => $histRate->rate,
                    'rate_sell' => $histRate->rate,
                    'source' => "copied_from_{$targetDate}",
                    'fetched_at' => now(),
                ]);

                $copied[] = $histRate->currency_code;
            }
        }

        $this->success('Rates copied successfully from '.$targetDate);
        $this->loadRates();
    }

    public function updatedSelectedBranchId(): void
    {
        $this->loadRates();
        $this->loadAvailableDates();
    }

    public function render(): View
    {
        return view('livewire.rates.index');
    }
}
