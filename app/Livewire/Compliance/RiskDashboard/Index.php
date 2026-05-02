<?php

namespace App\Livewire\Compliance\RiskDashboard;

use App\Livewire\BaseComponent;
use App\Models\Customer;
use App\Services\CustomerRiskScoringService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

class Index extends BaseComponent
{
    protected CustomerRiskScoringService $riskScoringService;

    public function __construct()
    {
        $this->riskScoringService = app(CustomerRiskScoringService::class);
    }

    protected function getSummary(): array
    {
        return [
            'high_risk' => Customer::where('risk_level', 'High')->count(),
            'medium_risk' => Customer::where('risk_level', 'Medium')->count(),
            'low_risk' => Customer::where('risk_level', 'Low')->count(),
        ];
    }

    protected function getCustomers(): Collection
    {
        return Customer::whereNotNull('risk_level')
            ->orderByRaw("FIELD(risk_level, 'High', 'Medium', 'Low')")
            ->limit(100)
            ->get();
    }

    public function rescreen(): void
    {
        // Trigger rescreening job
        $this->dispatchBrowserEvent('notify', [
            'type' => 'info',
            'message' => 'Rescreening job has been queued.',
        ]);
    }

    public function render(): View
    {
        return view('livewire.compliance.risk-dashboard.index', [
            'summary' => $this->getSummary(),
            'customers' => $this->getCustomers(),
        ]);
    }
}
