<?php

namespace App\Livewire\Branches;

use App\Livewire\BaseComponent;
use App\Models\Branch;
use Illuminate\View\View;
use Livewire\WithPagination;

class Index extends BaseComponent
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public function mount(): void {}

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $query = Branch::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('code', 'like', '%'.$this->search.'%')
                    ->orWhere('name', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->typeFilter) {
            $query->where('type', $this->typeFilter);
        }

        $branches = $query->orderBy('code')->paginate(20);

        return view('livewire.branches.index', compact('branches'));
    }
}
