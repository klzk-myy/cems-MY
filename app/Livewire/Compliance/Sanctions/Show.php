<?php

namespace App\Livewire\Compliance\Sanctions;

use App\Jobs\Sanctions\ImportSanctionListJob;
use App\Livewire\BaseComponent;
use App\Models\SanctionEntry;
use App\Models\SanctionList;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\WithPagination;

class Show extends BaseComponent
{
    use WithPagination;

    public SanctionList $list;

    public string $search = '';

    public ?string $entityType = '';

    public ?string $status = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'entityType' => ['except' => ''],
        'status' => ['except' => ''],
    ];

    public function mount(SanctionList $list): void
    {
        $this->list = $list->load(['entries' => function ($query) {
            $query->limit(10)->orderBy('entity_name');
        }]);
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->entityType = '';
        $this->status = '';
        $this->resetPage();
    }

    public function triggerImport(): void
    {
        try {
            ImportSanctionListJob::dispatch($this->list);
            $this->success('Import triggered for '.$this->list->name);
            $this->list->refresh();
        } catch (\Exception $e) {
            $this->error('Failed to trigger import: '.$e->getMessage());
        }
    }

    protected function getEntries(): LengthAwarePaginator
    {
        $query = SanctionEntry::where('list_id', $this->list->id);

        // Search filter
        if (! empty($this->search)) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('entity_name', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhere('nationality', 'like', "%{$search}%");
            });
        }

        // Entity type filter
        if (! empty($this->entityType)) {
            $query->where('entity_type', $this->entityType);
        }

        // Status filter
        if (! empty($this->status)) {
            $query->where('status', $this->status);
        }

        return $query->orderBy('entity_name')->paginate(20);
    }

    public function render(): View
    {
        return view('livewire.compliance.sanctions.show', [
            'entries' => $this->getEntries(),
            'listTypes' => ['individual', 'entity'],
            'entryStatuses' => ['active', 'inactive', 'deleted'],
        ]);
    }
}
