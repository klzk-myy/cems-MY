<?php

namespace App\Livewire\Stock;

use App\Livewire\BaseComponent;
use App\Models\TillBalance;

class TillReport extends BaseComponent
{
    public string $tillId = '';

    public string $date = '';

    public array $balances = [];

    public function mount(string $tillId = '', ?string $date = null)
    {
        $this->tillId = $tillId ?: request('till_id', '');
        $this->date = $date ?: now()->toDateString();

        if ($this->tillId) {
            $this->loadBalances();
        }
    }

    public function loadBalances(): void
    {
        if (! $this->tillId) {
            return;
        }

        $balances = TillBalance::with(['currency', 'opener', 'closer'])
            ->where('till_id', $this->tillId)
            ->whereDate('date', $this->date)
            ->get();

        $this->balances = $balances->map(function ($balance) {
            return [
                'id' => $balance->id,
                'till_id' => $balance->till_id,
                'currency_code' => $balance->currency_code,
                'currency' => $balance->currency ? [
                    'code' => $balance->currency->code,
                    'name' => $balance->currency->name,
                ] : null,
                'opening_balance' => $balance->opening_balance,
                'closing_balance' => $balance->closing_balance,
                'variance' => $balance->variance,
                'opener' => $balance->opener ? [
                    'name' => $balance->opener->name,
                ] : null,
                'closer' => $balance->closer ? [
                    'name' => $balance->closer->name,
                ] : null,
                'closed_at' => $balance->closed_at,
            ];
        })->toArray();
    }

    public function updatedTillId(): void
    {
        $this->loadBalances();
    }

    public function updatedDate(): void
    {
        $this->loadBalances();
    }

    public function render()
    {
        return view('livewire.stock.till-report');
    }
}
