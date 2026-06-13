<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Customer;
use App\Models\RiskScoreSnapshot;
use App\Services\CustomerRiskScoringService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RiskDashboardController extends Controller
{
    public function __construct(
        protected CustomerRiskScoringService $riskScoringService
    ) {}

    public function index(Request $request)
    {
        $threshold = $request->get('threshold', 60);

        $customers = Customer::whereHas('riskScoreSnapshots', function ($query) use ($threshold) {
            $query->where('overall_score', '>=', $threshold);
        })
            ->with('latestRiskSnapshot')
            ->orderByDesc('latestRiskSnapshot.overall_score')
            ->paginate(25);

        $summary = $this->riskScoringService->getDashboardSummary();

        return view('compliance.risk-dashboard.index', compact('customers', 'summary', 'threshold'));
    }

    public function customer(Customer $customer)
    {
        $trends = $this->riskScoringService->getRiskTrend($customer->id, 6);

        return view('compliance.risk-dashboard.customer', compact('customer', 'trends'));
    }

    public function trends()
    {
        $needsRescreening = $this->riskScoringService->getCustomersNeedingRescreening();
        $highRiskTrend = $this->getHighRiskCustomerTrend();
        $alertVolumeTrend = $this->getAlertVolumeTrend();

        return view('compliance.risk-dashboard.trends', compact(
            'needsRescreening',
            'highRiskTrend',
            'alertVolumeTrend'
        ));
    }

    public function rescreen(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
        ]);

        $result = $this->riskScoringService->rescreenCustomer($request->customer_id);

        return redirect()->back()
            ->with('success', sprintf(
                'Rescreening complete. Score changed from %s to %s (%s)',
                $result['previous_score'] ?? 'N/A',
                $result['new_score'],
                $result['significant_change'] ? 'significant change' : 'no significant change'
            ));
    }

    /**
     * Get high-risk customer counts for the last 6 months.
     *
     * @return array<string, array<int, string>|array<int, int>>
     */
    private function getHighRiskCustomerTrend(): array
    {
        $months = $this->getLastSixMonths();
        $start = $months->first()->copy()->startOfMonth();
        $end = $months->last()->copy()->endOfMonth();
        $format = DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', snapshot_date)"
            : "DATE_FORMAT(snapshot_date, '%Y-%m')";

        $counts = RiskScoreSnapshot::query()
            ->where('overall_score', '>=', 60)
            ->whereBetween('snapshot_date', [$start, $end])
            ->selectRaw("{$format} as month, COUNT(DISTINCT customer_id) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        return [
            'labels' => $months->map(fn ($date) => $date->format('M'))->toArray(),
            'values' => $months->map(fn ($date) => (int) ($counts[$date->format('Y-m')] ?? 0))->toArray(),
        ];
    }

    /**
     * Get alert volume counts for the last 6 months.
     *
     * @return array<string, array<int, string>|array<int, int>>
     */
    private function getAlertVolumeTrend(): array
    {
        $months = $this->getLastSixMonths();

        return [
            'labels' => $months->map(fn ($date) => $date->format('M'))->toArray(),
            'values' => $months->map(fn ($date) => Alert::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count())
                ->toArray(),
        ];
    }

    /**
     * Get a collection of the last 6 month dates ending at the current month.
     *
     * @return Collection<int, Carbon>
     */
    private function getLastSixMonths(): Collection
    {
        return collect(range(5, 0))->map(fn (int $i) => now()->subMonthsNoOverflow($i));
    }
}
