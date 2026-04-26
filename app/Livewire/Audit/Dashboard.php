<?php

namespace App\Livewire\Audit;

use App\Livewire\BaseComponent;
use App\Models\SystemLog;
use App\Services\LogRotationService;
use Illuminate\View\View;

class Dashboard extends BaseComponent
{
    public array $stats = [];

    public array $severityCounts = [];

    public array $topActions = [];

    public array $recentLogs = [];

    public array $archiveStats = [];

    public function mount(): void
    {
        $this->loadStats();
        $this->loadRecentLogs();
        $this->loadArchiveStats();
    }

    protected function loadStats(): void
    {
        $this->stats = [
            'total_logs' => SystemLog::count(),
            'today_logs' => SystemLog::whereDate('created_at', today())->count(),
            'critical_logs' => SystemLog::where('severity', 'CRITICAL')->count(),
            'error_logs' => SystemLog::where('severity', 'ERROR')->count(),
        ];

        $severityData = SystemLog::selectRaw('COALESCE(severity, "INFO") as severity, COUNT(*) as count')
            ->groupBy('severity')
            ->get();

        $this->severityCounts = $severityData->pluck('count', 'severity')->toArray();
    }

    protected function loadRecentLogs(): void
    {
        $logs = SystemLog::with('user')
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $this->recentLogs = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'severity' => $log->severity ?? 'INFO',
                'user_name' => $log->user?->name ?? 'System',
                'description' => $log->description(),
                'created_at' => $log->created_at?->format('d M Y H:i') ?? 'N/A',
            ];
        })->toArray();

        $this->topActions = SystemLog::selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'action')
            ->toArray();
    }

    protected function loadArchiveStats(): void
    {
        $logRotationService = app(LogRotationService::class);
        $this->archiveStats = $logRotationService->getArchiveStats();
    }

    public function render(): View
    {
        return view('livewire.audit.dashboard');
    }
}
