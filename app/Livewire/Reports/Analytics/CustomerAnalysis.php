<?php

namespace App\Livewire\Reports\Analytics;

use App\Livewire\BaseComponent;
use App\Models\Customer;
use App\Services\MathService;
use Illuminate\View\View;

class CustomerAnalysis extends BaseComponent
{
    public array $topCustomers = [];

    public array $riskDistribution = [];

    public function mount(): void
    {
        $this->loadTopCustomers();
        $this->loadRiskDistribution();
    }

    protected function loadTopCustomers(): void
    {
        $mathService = app(MathService::class);

        $customers = Customer::withCount('transactions')
            ->withSum('transactions', 'amount_local')
            ->orderBy('transactions_count', 'desc')
            ->take(50)
            ->get();

        $this->topCustomers = $customers->map(function ($customer) use ($mathService) {
            $totalVolume = $customer->transactions_sum_amount_local ?? '0';
            $count = $customer->transactions_count ?? 0;

            return [
                'customer' => $customer,
                'id' => $customer->id,
                'full_name' => $customer->full_name,
                'transaction_count' => $count,
                'total_volume' => $totalVolume,
                'avg_transaction' => $count > 0
                    ? $mathService->divide((string) $totalVolume, (string) $count)
                    : '0',
                'first_transaction' => $customer->transactions()->min('created_at'),
                'last_transaction' => $customer->transactions()->max('created_at'),
                'risk_rating' => $customer->risk_rating?->label() ?? 'N/A',
            ];
        })->toArray();
    }

    protected function loadRiskDistribution(): void
    {
        $distribution = Customer::select('risk_rating')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('risk_rating')
            ->get();

        $this->riskDistribution = $distribution->map(function ($row) {
            return [
                'risk_rating' => $row->risk_rating ?? 'Unknown',
                'count' => (int) $row->count,
            ];
        })->toArray();
    }

    public function render(): View
    {
        return view('livewire.reports.analytics.customer-analysis');
    }
}
