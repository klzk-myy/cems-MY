<?php

namespace App\Livewire\Counters;

use App\Enums\CounterSessionStatus;
use App\Livewire\BaseComponent;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Services\CounterService;
use Illuminate\Support\Facades\Auth;

class Open extends BaseComponent
{
    public ?Counter $counter = null;

    public string $openingFloat = '';

    public ?string $notes = null;

    public array $availableCounters = [];

    public array $currencies = [];

    public function mount(?int $counterId = null)
    {
        $this->currencies = Currency::where('is_active', true)->get()->map(function ($c) {
            return ['id' => $c->id, 'code' => $c->code, 'name' => $c->name];
        })->toArray();

        if ($counterId) {
            $this->counter = Counter::findOrFail($counterId);
        } else {
            // Get first available counter
            $available = $this->getAvailableCounters();
            $this->counter = ! empty($available) ? Counter::find($available[0]['id']) : null;
        }
    }

    protected function getAvailableCounters(): array
    {
        $today = now()->toDateString();
        $openCounterIds = CounterSession::where('status', CounterSessionStatus::Open->value)
            ->whereDate('session_date', $today)
            ->pluck('counter_id')
            ->toArray();

        $this->availableCounters = Counter::active()
            ->whereNotIn('id', $openCounterIds)
            ->get()
            ->map(function ($c) {
                return ['id' => $c->id, 'code' => $c->code, 'name' => $c->name];
            })
            ->toArray();

        return $this->availableCounters;
    }

    public function save()
    {
        $this->validate([
            'openingFloat' => 'required|numeric|min:0',
        ]);

        if (! $this->counter) {
            $this->error('No counter available');

            return;
        }

        try {
            $user = Auth::user();
            $openingFloats = [
                ['currency_id' => 'MYR', 'amount' => (float) $this->openingFloat],
            ];

            app(CounterService::class)->openSession(
                $this->counter,
                $user,
                $openingFloats
            );

            $this->success('Counter opened successfully');

            return redirect()->route('counters.index');
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.counters.open');
    }
}
