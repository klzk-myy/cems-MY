<?php

namespace App\Livewire\Transactions;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Livewire\BaseComponent;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\WithPagination;

class Index extends BaseComponent
{
    use WithPagination;

    public string $search = '';

    public ?string $type = '';

    public ?string $status = '';

    public ?string $dateFrom = '';

    public ?string $dateTo = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'type' => ['except' => ''],
        'status' => ['except' => ''],
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
        $this->type = '';
        $this->status = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    protected function getTransactions(): LengthAwarePaginator
    {
        $query = Transaction::with(['customer', 'user']);

        // Branch segregation: non-admin users can only see their branch's transactions
        $user = auth()->user();
        if ($user && $user->branch_id !== null) {
            $query->where('branch_id', $user->branch_id);
        }

        // Search filter - searches transaction ID or customer name
        if (! empty($this->search)) {
            $search = $this->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhereHas('customer', function (Builder $q) use ($search) {
                        $q->where('full_name', 'like', "%{$search}%");
                    });
            });
        }

        // Type filter
        if (! empty($this->type) && TransactionType::tryFrom($this->type) !== null) {
            $query->where('type', $this->type);
        }

        // Status filter
        if (! empty($this->status) && TransactionStatus::tryFrom($this->status) !== null) {
            $query->where('status', $this->status);
        }

        // Date range filters
        if (! empty($this->dateFrom)) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if (! empty($this->dateTo)) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        return $query->orderBy('created_at', 'desc')->paginate(20);
    }

    public function render(): View
    {
        $transactions = $this->getTransactions();

        return view('livewire.transactions.index', [
            'transactions' => $transactions,
            'transactionTypes' => TransactionType::cases(),
            'transactionStatuses' => TransactionStatus::cases(),
        ]);
    }
}
