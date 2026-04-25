<?php

namespace App\Livewire\Transactions;

use App\Livewire\BaseComponent;
use App\Models\Transaction;
use App\Services\TransactionMonitoringService;
use App\Services\TransactionService;
use Illuminate\View\View;
use Livewire\Redirector;

class Approve extends BaseComponent
{
    public ?Transaction $transaction = null;

    public array $amlResult = [];

    public bool $showConfirmation = false;

    public ?string $notes = null;

    public function mount(Transaction $transaction): void
    {
        $this->transaction = $transaction->load([
            'customer',
            'user',
            'branch',
            'counter',
        ]);

        // Run AML monitoring to get flags
        $monitoringService = app(TransactionMonitoringService::class);
        $this->amlResult = $monitoringService->monitorTransaction($this->transaction);
    }

    public function getHasHighPriorityFlagsAttribute(): bool
    {
        $highPriorityFlags = array_filter(
            $this->amlResult['flags'] ?? [],
            fn ($flag) => $flag->flag_type->isHighPriority()
        );

        return ! empty($highPriorityFlags);
    }

    public function confirmApproval(): void
    {
        $this->showConfirmation = true;
    }

    public function cancelApproval(): void
    {
        $this->showConfirmation = false;
        $this->notes = null;
    }

    public function approve(): Redirector
    {
        // Prevent self-approval (segregation of duties - AML/CFT compliance)
        if ($this->transaction->user_id === auth()->id()) {
            $this->error('You cannot approve your own transaction. Segregation of duties requires a different approver.');

            return $this->redirect(route('transactions.show', $this->transaction));
        }

        try {
            $transactionService = app(TransactionService::class);
            $result = $transactionService->approveTransaction(
                $this->transaction,
                auth()->id(),
                request()->ip()
            );

            if (! $result['success']) {
                $this->error($result['message']);

                return $this->redirect(route('transactions.show', $this->transaction));
            }

            $this->success('Transaction approved and completed successfully!');

            return $this->redirect(route('transactions.show', $this->transaction));

        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return $this->redirect(route('transactions.show', $this->transaction));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return $this->redirect(route('transactions.show', $this->transaction));
        } catch (\Exception $e) {
            $this->error('Approval failed: '.$e->getMessage());

            return $this->redirect(route('transactions.show', $this->transaction));
        }
    }

    public function render(): View
    {
        return view('livewire.transactions.approve');
    }
}
