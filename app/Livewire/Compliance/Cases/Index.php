<?php

namespace App\Livewire\Compliance\Cases;

use App\Enums\ComplianceCasePriority;
use App\Enums\ComplianceCaseStatus;
use App\Enums\ComplianceCaseType;
use App\Livewire\BaseComponent;
use App\Models\Compliance\ComplianceCase;
use App\Services\Compliance\CaseManagementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\WithPagination;

class Index extends BaseComponent
{
    use WithPagination;

    protected CaseManagementService $caseManagementService;

    public string $search = '';

    public ?string $status = '';

    public ?string $priority = '';

    public ?string $caseType = '';

    public ?string $assignedTo = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'priority' => ['except' => ''],
        'caseType' => ['except' => ''],
        'assignedTo' => ['except' => ''],
    ];

    public function __construct()
    {
        $this->caseManagementService = app(CaseManagementService::class);
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
        $this->caseType = '';
        $this->assignedTo = '';
        $this->resetPage();
    }

    protected function getCases(): LengthAwarePaginator
    {
        $query = ComplianceCase::with(['customer', 'assignee', 'alerts']);

        // Search filter
        if (! empty($this->search)) {
            $search = $this->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('case_number', 'like', "%{$search}%")
                    ->orWhere('case_summary', 'like', "%{$search}%")
                    ->orWhereHas('customer', function (Builder $q) use ($search) {
                        $q->where('full_name', 'like', "%{$search}%");
                    });
            });
        }

        // Status filter
        if (! empty($this->status) && ComplianceCaseStatus::tryFrom($this->status) !== null) {
            $query->where('status', $this->status);
        }

        // Priority filter
        if (! empty($this->priority) && ComplianceCasePriority::tryFrom($this->priority) !== null) {
            $query->where('priority', $this->priority);
        }

        // Case type filter
        if (! empty($this->caseType) && ComplianceCaseType::tryFrom($this->caseType) !== null) {
            $query->where('case_type', $this->caseType);
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
            ->orderByRaw("FIELD(priority, 'Critical', 'High', 'Medium', 'Low')")
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    protected function getSummary(): array
    {
        return [
            'total' => ComplianceCase::count(),
            'open' => ComplianceCase::where('status', '!=', ComplianceCaseStatus::Closed)->count(),
            'escalated' => ComplianceCase::where('status', ComplianceCaseStatus::Escalated)->count(),
            'closed' => ComplianceCase::where('status', ComplianceCaseStatus::Closed)->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.compliance.cases.index', [
            'cases' => $this->getCases(),
            'summary' => $this->getSummary(),
            'caseStatuses' => ComplianceCaseStatus::cases(),
            'casePriorities' => ComplianceCasePriority::cases(),
            'caseTypes' => ComplianceCaseType::cases(),
        ]);
    }
}
