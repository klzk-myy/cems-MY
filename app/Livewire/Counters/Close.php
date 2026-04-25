<?php

namespace App\Livewire\Counters;

use App\Enums\CounterSessionStatus;
use App\Livewire\BaseComponent;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Services\CounterService;
use Illuminate\Support\Facades\Auth;

class Close extends BaseComponent
{
    public Counter $counter;

    public ?CounterSession $session = null;

    public string $closingFloat = '';

    public string $cashInHand = '';

    public ?string $notes = null;

    public array $currencies = [];

    public function mount(Counter $counter)
    {
        $this->counter = $counter;
        $this->currencies = Currency::where('is_active', true)->get()->map(function ($c) {
            return ['id' => $c->id, 'code' => $c->code, 'name' => $c->name];
        })->toArray();

        $today = now()->toDateString();
        $this->session = CounterSession::where('counter_id', $counter->id)
            ->whereDate('session_date', $today)
            ->where('status', CounterSessionStatus::Open->value)
            ->with('user')
            ->first();

        if (! $this->session) {
            abort(400, 'No active session for this counter');
        }
    }

    public function save()
    {
        $this->validate([
            'closingFloat' => 'required|numeric|min:0',
            'cashInHand' => 'required|numeric|min:0',
        ]);

        try {
            $user = Auth::user();
            $closingFloats = [
                ['currency_id' => 'MYR', 'amount' => (float) $this->closingFloat],
            ];

            app(CounterService::class)->closeSession(
                $this->session,
                $user,
                $closingFloats,
                $this->notes
            );

            $this->success('Counter closed successfully');

            return redirect()->route('counters.index');
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.counters.close');
    }
}
