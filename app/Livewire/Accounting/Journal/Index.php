<?php

namespace App\Livewire\Accounting\Journal;

use App\Enums\JournalEntryStatus;
use App\Livewire\BaseComponent;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\WithPagination;

class Index extends BaseComponent
{
    use WithPagination;

    public string $search = '';

    public ?string $status = '';

    public ?string $entryType = '';

    public ?string $dateFrom = '';

    public ?string $dateTo = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'entryType' => ['except' => ''],
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
        $this->status = '';
        $this->entryType = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    protected function getEntries(): LengthAwarePaginator
    {
        $query = JournalEntry::with(['lines', 'creator', 'approver']);

        // Search filter
        if (! empty($this->search)) {
            $search = $this->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('entry_number', 'like', "%{$search}%");
            });
        }

        // Status filter
        if (! empty($this->status) && JournalEntryStatus::tryFrom($this->status) !== null) {
            $query->where('status', $this->status);
        }

        // Reference type filter
        if (! empty($this->entryType)) {
            $query->where('reference_type', $this->entryType);
        }

        // Date range filters
        if (! empty($this->dateFrom)) {
            $query->whereDate('entry_date', '>=', $this->dateFrom);
        }

        if (! empty($this->dateTo)) {
            $query->whereDate('entry_date', '<=', $this->dateTo);
        }

        return $query->orderBy('created_at', 'desc')->paginate(15);
    }

    public function render(): View
    {
        $entries = $this->getEntries();

        return view('livewire.accounting.journal.index', [
            'entries' => $entries,
            'statuses' => JournalEntryStatus::cases(),
            'referenceTypes' => $this->getReferenceTypes(),
        ]);
    }

    protected function getReferenceTypes(): array
    {
        return JournalEntry::query()
            ->select('reference_type')
            ->distinct()
            ->whereNotNull('reference_type')
            ->pluck('reference_type')
            ->toArray();
    }
}
