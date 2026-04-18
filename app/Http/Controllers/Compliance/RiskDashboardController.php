<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerRiskScoringService;
use Illuminate\Http\Request;

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

        return view('compliance.risk-dashboard.trends', compact('needsRescreening'));
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
}
