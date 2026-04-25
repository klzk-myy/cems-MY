<?php

namespace App\Livewire\Accounting\Reconciliation;

use App\Livewire\BaseComponent;
use App\Models\BankReconciliation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\WithPagination;

class Index extends BaseComponent
{
    use WithPagination;

    public string $search = '';

    public ?string $status = '';

    public ?string $dateFrom = '';

    public ?string $dateTo = '';

    public ?string $accountCode = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
        'accountCode' => ['except' => ''],
    ];

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->accountCode = '';
        $this->resetPage();
    }

    protected function getReconciliations(): LengthAwarePaginator
    {
        $query = BankReconciliation::with(['account', 'creator', 'matchedEntry']);

        // Search filter
        if (! empty($this->search)) {
            $search = $this->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Status filter
        if (! empty($this->status)) {
            $query->where('status', $this->status);
        }

        // Account filter
        if (! empty($this->accountCode)) {
            $query->where('account_code', $this->accountCode);
        }

        // Date range filters
        if (! empty($this->dateFrom)) {
            $query->whereDate('statement_date', '>=', $this->dateFrom);
        }

        if (! empty($this->dateTo)) {
            $query->whereDate('statement_date', '<=', $this->dateTo);
        }

        return $query->orderBy('statement_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    protected function getAccountCodes(): array
    {
        return BankReconciliation::query()
            ->select('account_code')
            ->distinct()
            ->whereNotNull('account_code')
            ->orderBy('account_code')
            ->pluck('account_code')
            ->toArray();
    }

    public function getStatusOptions(): array
    {
        return [
            ['value' => 'matched', 'label' => 'Matched'],
            ['value' => 'unmatched', 'label' => 'Unmatched'],
            ['value' => 'exception', 'label' => 'Exception'],
        ];
    }

    public function render(): View
    {
        $reconciliations = $this->getReconciliations();

        return view('livewire.accounting.reconciliation.index', [
            'reconciliations' => $reconciliations,
            'accountCodes' => $this->getAccountCodes(),
            'statusOptions' => $this->getStatusOptions(),
        ]);
    }
}
