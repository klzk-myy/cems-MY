<?php

namespace App\Livewire\Transactions;

use App\Enums\TransactionStatus;
use App\Livewire\BaseComponent;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionCancellationService;
use Illuminate\View\View;

class Cancel extends BaseComponent
{
    public ?Transaction $transaction = null;

    public bool $showConfirmation = false;

    public ?string $action = null; // 'approve' or 'reject'

    public ?string $reason = null;

    public function mount(Transaction $transaction): void
    {
        $this->transaction = $transaction->load([
            'customer',
            'user',
            'branch',
            'counter',
        ]);

        // Must be in PendingCancellation status
        if (! $this->transaction->status->isPendingCancellation()) {
            $this->error('This transaction is not pending cancellation.');
            $this->redirect(route('transactions.show', $this->transaction));
        }
    }

    public function getCancellationDetailsAttribute(): array
    {
        $history = $this->transaction->transition_history ?? [];

        // Find the cancellation request details
        foreach (array_reverse($history) as $entry) {
            if (($entry['to'] ?? '') === TransactionStatus::PendingCancellation->value) {
                return [
                    'reason' => $entry['reason'] ?? 'No reason provided',
                    'requested_by' => $entry['user_id'] ?? null,
                    'requested_at' => $entry['timestamp'] ?? null,
                ];
            }
        }

        return [
            'reason' => 'No reason provided',
            'requested_by' => null,
            'requested_at' => null,
        ];
    }

    public function getRequestedByUserAttribute(): ?User
    {
        $details = $this->cancellationDetails;
        if (! $details['requested_by']) {
            return null;
        }

        return User::find($details['requested_by']);
    }

    public function confirmApprove(): void
    {
        $this->action = 'approve';
        $this->showConfirmation = true;
    }

    public function confirmReject(): void
    {
        $this->action = 'reject';
        $this->showConfirmation = true;
    }

    public function cancelAction(): void
    {
        $this->showConfirmation = false;
        $this->action = null;
        $this->reason = null;
    }

    public function processApproval(): mixed
    {
        $this->validate([
            'reason' => 'required_if:action,reject|nullable|string|max:500',
        ]);

        $cancellationService = app(TransactionCancellationService::class);

        if ($this->action === 'approve') {
            return $this->approveCancellation($cancellationService);
        }

        if ($this->action === 'reject') {
            return $this->rejectCancellation($cancellationService);
        }

        return $this->redirect(route('transactions.show', $this->transaction));
    }

    protected function approveCancellation(TransactionCancellationService $cancellationService): mixed
    {
        // Prevent self-approval (segregation of duties - AML/CFT compliance)
        $details = $this->cancellationDetails;
        if ($details['requested_by'] === auth()->id()) {
            $this->error('You cannot approve your own cancellation request. Segregation of duties requires a different approver.');

            return $this->redirect(route('transactions.show', $this->transaction));
        }

        try {
            $result = $cancellationService->approveCancellation(
                $this->transaction,
                auth()->user(),
                $this->reason
            );

            if (! $result) {
                $this->error('Failed to approve cancellation. The transaction may have already been processed.');

                return $this->redirect(route('transactions.show', $this->transaction));
            }

            $this->success('Cancellation approved. Transaction has been cancelled.');

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

    protected function rejectCancellation(TransactionCancellationService $cancellationService): mixed
    {
        if (empty($this->reason)) {
            $this->error('Rejection reason is required.');

            return null;
        }

        try {
            $result = $cancellationService->rejectCancellation(
                $this->transaction,
                auth()->user(),
                $this->reason
            );

            if (! $result) {
                $this->error('Failed to reject cancellation. The transaction may have already been processed.');

                return $this->redirect(route('transactions.show', $this->transaction));
            }

            $this->success('Cancellation rejected. Transaction has been returned to its previous status.');

            return $this->redirect(route('transactions.show', $this->transaction));

        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return $this->redirect(route('transactions.show', $this->transaction));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return $this->redirect(route('transactions.show', $this->transaction));
        } catch (\Exception $e) {
            $this->error('Rejection failed: '.$e->getMessage());

            return $this->redirect(route('transactions.show', $this->transaction));
        }
    }

    public function render(): View
    {
        return view('livewire.transactions.cancel');
    }
}
