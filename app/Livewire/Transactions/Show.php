<?php

namespace App\Livewire\Transactions;

use App\Enums\TransactionStatus;
use App\Livewire\BaseComponent;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;

class Show extends BaseComponent
{
    public ?Transaction $transaction = null;

    public function mount(Transaction $transaction): void
    {
        $this->transaction = $transaction->load([
            'customer',
            'user',
            'branch',
            'counter',
            'flags.flagType',
        ]);
    }

    #[Computed]
    protected function auditLogs(): Collection
    {
        return $this->transaction->systemLogs()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
    }

    public function getStatusClassAttribute(): string
    {
        return match ($this->transaction->status) {
            TransactionStatus::Completed => 'badge-success',
            TransactionStatus::PendingApproval => 'badge-warning',
            TransactionStatus::PendingCancellation => 'badge-warning',
            TransactionStatus::Cancelled => 'badge-danger',
            TransactionStatus::Pending => 'badge-warning',
            default => 'badge-default'
        };
    }

    public function render(): View
    {
        return view('livewire.transactions.show');
    }
}
