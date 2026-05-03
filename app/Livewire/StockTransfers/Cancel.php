<?php

namespace App\Livewire\StockTransfers;

use App\Livewire\BaseComponent;
use App\Models\StockTransfer;
use Illuminate\View\View;

class Cancel extends BaseComponent
{
    public ?StockTransfer $transfer = null;

    public ?string $reason = null;

    public function mount(StockTransfer $stockTransfer): void
    {
        $this->transfer = $stockTransfer;
    }

    public function cancel(): mixed
    {
        $this->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $this->transfer->cancel($this->reason);

            $this->success('Transfer cancelled.');

            return $this->redirect(route('stock-transfers.show', $this->transfer));
        } catch (\Exception $e) {
            $this->error('Failed to cancel: '.$e->getMessage());

            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.stock-transfers.cancel');
    }
}
