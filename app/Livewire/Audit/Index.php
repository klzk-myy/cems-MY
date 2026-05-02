<?php

namespace App\Livewire\Audit;

use App\Livewire\BaseComponent;
use App\Models\SystemLog;
use Illuminate\View\View;
use Livewire\WithPagination;

class Index extends BaseComponent
{
    use WithPagination;

    public string $search = '';

    public string $severityFilter = '';

    public string $actionFilter = '';

    public array $actions = [];

    public function mount(): void
    {
        $this->loadActions();
    }

    protected function loadActions(): void
    {
        $this->actions = SystemLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->toArray();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSeverityFilter(): void
    {
        $this->resetPage();
    }

    public function updatedActionFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $query = SystemLog::with('user');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('action', 'like', '%'.$this->search.'%')
                    ->orWhere('entity_type', 'like', '%'.$this->search.'%')
                    ->orWhereHas('user', function ($uq) {
                        $uq->where('name', 'like', '%'.$this->search.'%');
                    });
            });
        }

        if ($this->severityFilter) {
            $query->where('severity', $this->severityFilter);
        }

        if ($this->actionFilter) {
            $query->where('action', $this->actionFilter);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(30);

        return view('livewire.audit.index', compact('logs'));
    }
}
