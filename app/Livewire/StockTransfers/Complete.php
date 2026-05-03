<?php

namespace App\Livewire\StockTransfers;

use App\Livewire\BaseComponent;
use App\Models\StockTransfer;
use Illuminate\View\View;

class Complete extends BaseComponent
{
    public ?StockTransfer $transfer = null;

    public function mount(StockTransfer $stockTransfer): void
    {
        $this->transfer = $stockTransfer;
    }

    public function complete(): mixed
    {
        try {
            $this->transfer->complete();

            $this->success('Transfer completed.');

            return $this->redirect(route('stock-transfers.show', $this->transfer));
        } catch (\Exception $e) {
            $this->error('Failed to complete: '.$e->getMessage());

            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.stock-transfers.complete');
    }
}
