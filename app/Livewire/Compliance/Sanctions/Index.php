<?php

namespace App\Livewire\Compliance\Sanctions;

use App\Jobs\Sanctions\ImportSanctionListJob;
use App\Livewire\BaseComponent;
use App\Models\SanctionList;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

class Index extends BaseComponent
{
    public string $search = '';

    public ?string $status = '';

    public function applyFilters(): void
    {
        // Filters are applied reactively via computed properties
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = '';
    }

    public function triggerImport(SanctionList $list): void
    {
        try {
            // Dispatch job to import sanctions list
            ImportSanctionListJob::dispatch($list);
            $this->success('Import triggered for '.$list->name);
        } catch (\Exception $e) {
            $this->error('Failed to trigger import: '.$e->getMessage());
        }
    }

    protected function getLists(): Collection
    {
        $query = SanctionList::query();

        if (! empty($this->search)) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('list_type', 'like', "%{$search}%");
            });
        }

        if ($this->status !== '') {
            $isActive = $this->status === 'active';
            $query->where('is_active', $isActive);
        }

        return $query->orderBy('name')->get();
    }

    protected function getSummary(): array
    {
        $lists = SanctionList::all();

        return [
            'total_lists' => $lists->count(),
            'active_lists' => $lists->where('is_active', true)->count(),
            'total_entries' => $lists->sum('entry_count'),
            'last_import' => $lists->whereNotNull('last_updated_at')->sortByDesc('last_updated_at')->first()?->last_updated_at,
        ];
    }

    public function render(): View
    {
        return view('livewire.compliance.sanctions.index', [
            'lists' => $this->getLists(),
            'summary' => $this->getSummary(),
        ]);
    }
}
