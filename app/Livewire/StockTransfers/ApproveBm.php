<?php

namespace App\Livewire\StockTransfers;

use App\Livewire\BaseComponent;
use App\Models\StockTransfer;
use Illuminate\View\View;

class ApproveBm extends BaseComponent
{
    public ?StockTransfer $transfer = null;

    public ?string $note = null;

    public function mount(StockTransfer $stockTransfer): void
    {
        $this->transfer = $stockTransfer->load([
            'fromBranch',
            'toBranch',
            'creator',
            'items',
        ]);
    }

    public function approve(): mixed
    {
        $this->validate([
            'note' => 'nullable|string|max:500',
        ]);

        try {
            $this->transfer->approveBm($this->note);

            $this->success('Transfer approved by branch manager.');

            return $this->redirect(route('stock-transfers.show', $this->transfer));
        } catch (\Exception $e) {
            $this->error('Failed to approve: '.$e->getMessage());

            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.stock-transfers.approve-bm');
    }
}
