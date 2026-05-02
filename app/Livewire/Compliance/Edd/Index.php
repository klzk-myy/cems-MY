<?php

namespace App\Livewire\Compliance\Edd;

use App\Enums\EddRiskLevel;
use App\Enums\EddStatus;
use App\Livewire\BaseComponent;
use App\Models\EnhancedDiligenceRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\WithPagination;

class Index extends BaseComponent
{
    use WithPagination;

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'riskLevel' => ['except' => ''],
    ];

    public string $search = '';

    public ?string $status = '';

    public ?string $riskLevel = '';

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->riskLevel = '';
        $this->resetPage();
    }

    protected function getRecords(): LengthAwarePaginator
    {
        $query = EnhancedDiligenceRecord::with(['customer', 'reviewer']);

        // Search filter
        if (! empty($this->search)) {
            $search = $this->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('edd_reference', 'like', "%{$search}%")
                    ->orWhereHas('customer', function (Builder $q) use ($search) {
                        $q->where('full_name', 'like', "%{$search}%");
                    });
            });
        }

        // Status filter
        if (! empty($this->status) && EddStatus::tryFrom($this->status) !== null) {
            $query->where('status', $this->status);
        }

        // Risk level filter
        if (! empty($this->riskLevel) && EddRiskLevel::tryFrom($this->riskLevel) !== null) {
            $query->where('risk_level', $this->riskLevel);
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    protected function getSummary(): array
    {
        return [
            'total' => EnhancedDiligenceRecord::count(),
            'pending_review' => EnhancedDiligenceRecord::where('status', EddStatus::PendingReview)->count(),
            'approved' => EnhancedDiligenceRecord::where('status', EddStatus::Approved)->count(),
            'rejected' => EnhancedDiligenceRecord::where('status', EddStatus::Rejected)->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.compliance.edd.index', [
            'records' => $this->getRecords(),
            'summary' => $this->getSummary(),
            'eddStatuses' => EddStatus::cases(),
            'eddRiskLevels' => EddRiskLevel::cases(),
        ]);
    }
}
