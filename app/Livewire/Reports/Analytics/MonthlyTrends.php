<?php

namespace App\Livewire\Reports\Analytics;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Livewire\BaseComponent;
use App\Models\Currency;
use App\Models\Transaction;
use App\Services\MathService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MonthlyTrends extends BaseComponent
{
    public int $year;

    public string $currency;

    public array $monthlyData = [];

    public array $trends = [];

    public array $currencies = [];

    public function mount(): void
    {
        $this->year = (int) now()->year;
        $this->currency = 'all';
        $this->loadMonthlyData();
        $this->loadCurrencies();
    }

    protected function loadMonthlyData(): void
    {
        $query = Transaction::whereYear('created_at', $this->year)
            ->where('status', TransactionStatus::Completed);

        if ($this->currency !== 'all') {
            $query->where('currency_code', $this->currency);
        }

        $data = $query->select(
            DB::raw('MONTH(created_at) as month'),
            DB::raw('COUNT(*) as count'),
            DB::raw("SUM(CASE WHEN type = '".TransactionType::Buy->value."' THEN amount_local ELSE 0 END) as buy_volume"),
            DB::raw("SUM(CASE WHEN type = '".TransactionType::Sell->value."' THEN amount_local ELSE 0 END) as sell_volume"),
            DB::raw('SUM(amount_local) as total_volume')
        )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $this->monthlyData = $data->map(function ($row) {
            return [
                'month' => (int) $row->month,
                'count' => (int) $row->count,
                'buy_volume' => $row->buy_volume ?? '0',
                'sell_volume' => $row->sell_volume ?? '0',
                'total_volume' => $row->total_volume ?? '0',
            ];
        })->toArray();

        $this->calculateTrends();
    }

    protected function calculateTrends(): void
    {
        $mathService = app(MathService::class);
        $previousVolume = null;

        $this->trends = [];
        foreach ($this->monthlyData as $row) {
            $trend = null;
            if ($previousVolume !== null && bccomp($previousVolume, '0', 4) > 0) {
                $diff = $mathService->subtract((string) $row['total_volume'], (string) $previousVolume);
                $trend = $mathService->multiply(
                    $mathService->divide($diff, (string) $previousVolume),
                    '100'
                );
            }

            $comparison = $trend !== null ? bccomp($trend, '0', 4) : 0;
            $this->trends[$row['month']] = [
                'volume' => $row['total_volume'],
                'trend' => $trend,
                'direction' => $comparison > 0 ? 'up' : ($comparison < 0 ? 'down' : 'neutral'),
            ];
            $previousVolume = $row['total_volume'];
        }
    }

    protected function loadCurrencies(): void
    {
        $this->currencies = Currency::where('is_active', true)
            ->pluck('code')
            ->toArray();
    }

    public function updatedYear(): void
    {
        $this->loadMonthlyData();
    }

    public function updatedCurrency(): void
    {
        $this->loadMonthlyData();
    }

    public function render(): View
    {
        return view('livewire.reports.analytics.monthly-trends');
    }
}
