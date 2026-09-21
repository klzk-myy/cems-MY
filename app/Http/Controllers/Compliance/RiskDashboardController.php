<?php

namespace App\Http\Controllers\Compliance;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\RescreenCustomerRequest;
use App\Models\Compliance\Alert;
use App\Models\Compliance\SanctionEntry;
use App\Models\Customer;
use App\Models\RiskScoreSnapshot;
use App\Services\Compliance\CustomerRiskScoringService;
use App\Support\DbDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class RiskDashboardController extends Controller
{
    public function __construct(
        protected CustomerRiskScoringService $riskScoringService
    ) {}

    public function index(Request $request): View
    {
        $this->requirePermission(Permission::ViewRiskDashboard);

        $threshold = $request->get('threshold', 60);

        $customers = Customer::whereHas('latestRiskSnapshot', function ($query) use ($threshold) {
            $query->where('overall_score', '>=', $threshold);
        })
            ->with('latestRiskSnapshot')
            // The ordering subquery must mirror latestRiskSnapshot's
            // latestOfMany('snapshot_date') tiebreak (greatest id on a
            // same-date tie), or a row can be ordered by a score other than
            // the snapshot the eager load displays.
            ->orderByDesc(
                RiskScoreSnapshot::select('overall_score')
                    ->whereColumn('customer_id', 'customers.id')
                    ->latest('snapshot_date')
                    ->latest('id')
                    ->limit(1)
            )
            ->paginate(25);

        $summary = $this->riskScoringService->getDashboardSummary();

        return view('compliance.risk-dashboard.index', compact('customers', 'summary', 'threshold'))
            ->with('sanctionsLoaded', SanctionEntry::exists());
    }

    public function customer(Customer $customer): View
    {
        $this->requirePermission(Permission::ViewRiskDashboard);

        $trends = $this->riskScoringService->getRiskTrend($customer->id, 6);

        $customer->load([
            'riskHistory' => fn ($query) => $query->latest()->limit(10),
            'riskHistory.assessor',
        ]);

        return view('compliance.risk-dashboard.customer', compact('customer', 'trends'));
    }

    public function trends(): View
    {
        $this->requirePermission(Permission::ViewRiskDashboard);

        $needsRescreening = $this->riskScoringService->getCustomersNeedingRescreening()->paginate(20);
        $highRiskTrend = $this->getHighRiskCustomerTrend();
        $alertVolumeTrend = $this->getAlertVolumeTrend();

        return view('compliance.risk-dashboard.trends', compact(
            'needsRescreening',
            'highRiskTrend',
            'alertVolumeTrend'
        ));
    }

    public function rescreen(RescreenCustomerRequest $request): RedirectResponse
    {
        $this->requirePermission(Permission::ManageRiskScreening);

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
        $format = DbDate::monthBucket('snapshot_date');

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
        $start = $months->first()->copy()->startOfMonth();
        $end = $months->last()->copy()->endOfMonth();
        $format = DbDate::monthBucket('created_at');

        $counts = Alert::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("{$format} as month, COUNT(*) as count")
            ->groupBy('month')
            ->pluck('count', 'month');

        return [
            'labels' => $months->map(fn ($date) => $date->format('M'))->toArray(),
            'values' => $months->map(fn ($date) => (int) ($counts[$date->format('Y-m')] ?? 0))->toArray(),
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
