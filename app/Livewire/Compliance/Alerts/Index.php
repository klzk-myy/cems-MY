<?php

namespace App\Livewire\Compliance\Alerts;

use App\Enums\AlertPriority;
use App\Enums\FlagStatus;
use App\Livewire\BaseComponent;
use App\Models\Alert;
use App\Services\AlertTriageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\WithPagination;

class Index extends BaseComponent
{
    use WithPagination;

    protected AlertTriageService $alertTriageService;

    public string $search = '';

    public ?string $status = '';

    public ?string $priority = '';

    public ?string $assignedTo = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'priority' => ['except' => ''],
        'assignedTo' => ['except' => ''],
    ];

    public function __construct()
    {
        $this->alertTriageService = app(AlertTriageService::class);
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->priority = '';
        $this->assignedTo = '';
        $this->resetPage();
    }

    protected function getAlerts(): LengthAwarePaginator
    {
        $query = Alert::with(['customer', 'assignedTo', 'flaggedTransaction']);

        // Search filter
        if (! empty($this->search)) {
            $search = $this->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('customer', function (Builder $q) use ($search) {
                        $q->where('full_name', 'like', "%{$search}%");
                    });
            });
        }

        // Status filter
        if (! empty($this->status) && FlagStatus::tryFrom($this->status) !== null) {
            $query->where('status', $this->status);
        }

        // Priority filter
        if (! empty($this->priority) && AlertPriority::tryFrom($this->priority) !== null) {
            $query->where('priority', $this->priority);
        }

        // Assigned to filter
        if (! empty($this->assignedTo)) {
            if ($this->assignedTo === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $this->assignedTo);
            }
        }

        return $query
            ->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    protected function getSummary(): array
    {
        $baseQuery = Alert::whereNull('case_id');

        return [
            'total' => $baseQuery->count(),
            'pending' => $baseQuery->where('status', FlagStatus::Open)->count(),
            'in_progress' => $baseQuery->where('status', FlagStatus::UnderReview)->count(),
            'resolved_today' => Alert::whereDate('updated_at', today())
                ->where('status', FlagStatus::Resolved)->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.compliance.alerts.index', [
            'alerts' => $this->getAlerts(),
            'summary' => $this->getSummary(),
            'alertStatuses' => FlagStatus::cases(),
            'alertPriorities' => AlertPriority::cases(),
        ]);
    }
}
