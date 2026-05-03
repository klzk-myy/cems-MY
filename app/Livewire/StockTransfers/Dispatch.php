<?php

namespace App\Livewire\StockTransfers;

use App\Livewire\BaseComponent;
use App\Models\StockTransfer;
use Illuminate\View\View;

class Dispatch extends BaseComponent
{
    public ?StockTransfer $transfer = null;

    public ?string $note = null;

    public function mount(StockTransfer $stockTransfer): void
    {
        $this->transfer = $stockTransfer->load([
            'fromBranch',
            'toBranch',
            'hqApprover',
            'items',
        ]);
    }

    public function dispatchTransfer(): mixed
    {
        $this->validate([
            'note' => 'nullable|string|max:500',
        ]);

        try {
            $this->transfer->dispatch($this->note);

            $this->success('Transfer dispatched successfully.');

            return $this->redirect(route('stock-transfers.show', $this->transfer));
        } catch (\Exception $e) {
            $this->error('Failed to dispatch transfer: '.$e->getMessage());

            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.stock-transfers.dispatch');
    }
}
