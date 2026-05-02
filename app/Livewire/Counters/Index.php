<?php

namespace App\Livewire\Counters;

use App\Enums\CounterSessionStatus;
use App\Livewire\BaseComponent;
use App\Models\Counter;
use Illuminate\Support\Facades\Auth;

class Index extends BaseComponent
{
    public array $counters = [];

    public function mount()
    {
        $this->loadCounters();
    }

    public function loadCounters()
    {
        $today = now()->toDateString();

        $this->counters = Counter::with(['branch', 'sessions' => function ($query) use ($today) {
            $query->whereDate('session_date', $today)
                ->where('status', CounterSessionStatus::Open->value);
        }])->get()->map(function ($counter) {
            $openSession = $counter->sessions->first();
            $statusValue = $openSession
                ? CounterSessionStatus::Open->value
                : CounterSessionStatus::Closed->value;

            return [
                'id' => $counter->id,
                'code' => $counter->code,
                'name' => $counter->name,
                'status' => $statusValue,
                'status_label' => $openSession
                    ? CounterSessionStatus::Open->label()
                    : CounterSessionStatus::Closed->label(),
                'branch_name' => $counter->branch->name ?? 'N/A',
                'operator_name' => $openSession ? ($openSession->user->username ?? 'Unknown') : 'Unassigned',
                'opening_float' => $openSession ? $openSession->opening_float ?? 0 : 0,
                'session_id' => $openSession?->id,
            ];
        })->toArray();
    }

    public function getUserOpenCounter(): ?array
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        return collect($this->counters)->first(function ($c) use ($user) {
            return $c['status'] === 'Open' && $c['operator_name'] === $user->username;
        });
    }

    public function render()
    {
        return view('livewire.counters.index');
    }
}
