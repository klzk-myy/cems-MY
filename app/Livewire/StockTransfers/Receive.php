<?php

namespace App\Livewire\StockTransfers;

use App\Livewire\BaseComponent;
use App\Models\StockTransfer;
use Illuminate\View\View;

class Receive extends BaseComponent
{
    public ?StockTransfer $transfer = null;

    public array $received = [];

    public ?string $note = null;

    public function mount(StockTransfer $stockTransfer): void
    {
        $this->transfer = $stockTransfer->load([
            'fromBranch',
            'toBranch',
            'dispatcher',
            'items',
        ]);

        foreach ($this->transfer->items ?? [] as $item) {
            $this->received[$item->id] = $item->quantity_sent ?? 0;
        }
    }

    public function receive(): mixed
    {
        $this->validate([
            'received' => 'required|array',
            'received.*' => 'required|numeric|min:0',
            'note' => 'nullable|string|max:500',
        ]);

        try {
            $this->transfer->receiveItems($this->received, $this->note);

            $this->success('Items received successfully.');

            return $this->redirect(route('stock-transfers.show', $this->transfer));
        } catch (\Exception $e) {
            $this->error('Failed to receive items: '.$e->getMessage());

            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.stock-transfers.receive');
    }
}
