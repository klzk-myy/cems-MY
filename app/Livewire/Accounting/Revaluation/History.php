<?php

namespace App\Livewire\Accounting\Revaluation;

use App\Livewire\BaseComponent;
use App\Models\RevaluationEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\WithPagination;

class History extends BaseComponent
{
    use WithPagination;

    public string $search = '';

    public ?string $currencyCode = '';

    public ?string $dateFrom = '';

    public ?string $dateTo = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'currencyCode' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
    ];

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->currencyCode = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    protected function getEntries(): LengthAwarePaginator
    {
        $query = RevaluationEntry::with(['currency', 'postedBy']);

        // Search filter
        if (! empty($this->search)) {
            $search = $this->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('currency_code', 'like', "%{$search}%");
            });
        }

        // Currency filter
        if (! empty($this->currencyCode)) {
            $query->where('currency_code', $this->currencyCode);
        }

        // Date range filters
        if (! empty($this->dateFrom)) {
            $query->whereDate('revaluation_date', '>=', $this->dateFrom);
        }

        if (! empty($this->dateTo)) {
            $query->whereDate('revaluation_date', '<=', $this->dateTo);
        }

        return $query->orderBy('revaluation_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    protected function getCurrencies(): array
    {
        return RevaluationEntry::query()
            ->select('currency_code')
            ->distinct()
            ->whereNotNull('currency_code')
            ->orderBy('currency_code')
            ->pluck('currency_code')
            ->toArray();
    }

    public function render(): View
    {
        $entries = $this->getEntries();

        return view('livewire.accounting.revaluation.history', [
            'entries' => $entries,
            'currencies' => $this->getCurrencies(),
        ]);
    }
}
