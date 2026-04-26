<?php

namespace App\Livewire\Customers;

use App\Livewire\BaseComponent;
use App\Models\Customer;
use Illuminate\View\View;
use Livewire\WithPagination;

class Index extends BaseComponent
{
    use WithPagination;

    public string $search = '';

    public string $riskRating = '';

    public string $statusFilter = '';

    public string $pepFilter = '';

    public string $nationalityFilter = '';

    public array $riskRatings = [];

    public array $nationalities = [];

    public function mount(): void
    {
        $this->riskRatings = ['Low', 'Medium', 'High'];
        $this->nationalities = Customer::distinct()->pluck('nationality')->sort()->toArray();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRiskRating(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPepFilter(): void
    {
        $this->resetPage();
    }

    public function updatedNationalityFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $query = Customer::query()->withCount(['documents', 'transactions']);

        if ($this->search) {
            $search = addcslashes($this->search, '%_');
            $query->where('full_name', 'like', '%'.$search.'%');
        }

        if ($this->riskRating) {
            $query->where('risk_rating', $this->riskRating);
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        if ($this->pepFilter === '1') {
            $query->where('pep_status', true);
        } elseif ($this->pepFilter === '0') {
            $query->where('pep_status', false);
        }

        if ($this->nationalityFilter) {
            $query->where('nationality', $this->nationalityFilter);
        }

        $customers = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('livewire.customers.index', compact('customers'));
    }
}
