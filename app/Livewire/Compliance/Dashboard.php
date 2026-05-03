<?php

namespace App\Livewire\Compliance;

use App\Enums\FlagStatus;
use App\Livewire\BaseComponent;
use App\Models\Alert;
use App\Models\Compliance\ComplianceCase;
use App\Models\EnhancedDiligenceRecord;
use App\Models\StrReport;
use App\Services\AlertTriageService;
use Illuminate\View\View;

class Dashboard extends BaseComponent
{
    protected AlertTriageService $alertTriageService;

    public function __construct()
    {
        $this->alertTriageService = app(AlertTriageService::class);
    }

    public function mount(): void
    {
        // Verify compliance access
    }

    protected function getStats(): array
    {
        $baseQuery = Alert::whereNull('case_id');

        return [
            'active_alerts' => $baseQuery->whereIn('status', [FlagStatus::Open, FlagStatus::UnderReview])->count(),
            'pending_review' => $baseQuery->where('status', FlagStatus::Open)->count(),
            'open_cases' => ComplianceCase::where('status', 'open')->count(),
            'cases_needing_attention' => ComplianceCase::where('status', 'open')
                ->where('priority', 'high')
                ->count(),
            'edd_records' => EnhancedDiligenceRecord::count(),
            'edd_pending' => EnhancedDiligenceRecord::where('status', 'pending')->count(),
            'str_submitted' => StrReport::where('status', 'submitted')->count(),
            'str_pending' => StrReport::where('status', 'draft')->count(),
        ];
    }

    protected function getRecentAlerts(): array
    {
        return Alert::with(['customer'])
            ->whereIn('status', [FlagStatus::Open, FlagStatus::UnderReview])
            ->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->toArray();
    }

    public function render(): View
    {
        return view('livewire.compliance.dashboard', [
            'stats' => $this->getStats(),
            'recent_alerts' => $this->getRecentAlerts(),
        ]);
    }
}
