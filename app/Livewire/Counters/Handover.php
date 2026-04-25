<?php

namespace App\Livewire\Counters;

use App\Enums\CounterSessionStatus;
use App\Enums\UserRole;
use App\Livewire\BaseComponent;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\User;
use App\Services\CounterService;
use Illuminate\Support\Facades\Auth;

class Handover extends BaseComponent
{
    public Counter $counter;

    public ?CounterSession $session = null;

    public ?int $transferToUserId = null;

    public ?int $supervisorId = null;

    public ?string $notes = null;

    public array $availableUsers = [];

    public array $supervisors = [];

    public array $currencies = [];

    public array $physicalCounts = [];

    public function mount(Counter $counter)
    {
        $this->counter = $counter;

        $today = now()->toDateString();
        $this->session = CounterSession::where('counter_id', $counter->id)
            ->whereDate('session_date', $today)
            ->where('status', CounterSessionStatus::Open->value)
            ->with('user')
            ->first();

        if (! $this->session) {
            abort(400, 'No active session for this counter');
        }

        $this->currencies = Currency::where('is_active', true)->get()->map(function ($c) {
            return ['id' => $c->id, 'code' => $c->code, 'name' => $c->name];
        })->toArray();

        // Default physical counts from session
        $this->physicalCounts = collect($this->currencies)->mapWithKeys(function ($c) {
            return [$c['code'] => '0'];
        })->toArray();

        $this->loadUsers();
    }

    protected function loadUsers()
    {
        $user = Auth::user();

        // Available users: all active users except current user
        $this->availableUsers = User::where('is_active', true)
            ->where('id', '!=', $user->id)
            ->get()
            ->map(function ($u) {
                return ['id' => $u->id, 'name' => $u->name, 'username' => $u->username];
            })
            ->toArray();

        // Supervisors: managers and admins
        $this->supervisors = User::where('is_active', true)
            ->whereIn('role', [UserRole::Manager->value, UserRole::Admin->value])
            ->get()
            ->map(function ($u) {
                return ['id' => $u->id, 'name' => $u->name, 'username' => $u->username];
            })
            ->toArray();
    }

    public function save()
    {
        $this->validate([
            'transferToUserId' => 'required|exists:users,id',
            'supervisorId' => 'required|exists:users,id',
        ]);

        try {
            $user = Auth::user();
            $toUser = User::findOrFail($this->transferToUserId);
            $supervisor = User::findOrFail($this->supervisorId);

            // Build physical counts from form data
            $physicalCounts = [];
            foreach ($this->currencies as $currency) {
                $code = $currency['code'];
                if (isset($this->physicalCounts[$code])) {
                    $amount = (float) $this->physicalCounts[$code];
                    if ($amount > 0) {
                        $physicalCounts[] = [
                            'currency_id' => $code,
                            'amount' => $amount,
                        ];
                    }
                }
            }

            app(CounterService::class)->initiateHandover(
                $this->session,
                $user,
                $toUser,
                $supervisor,
                $physicalCounts
            );

            $this->success('Counter handed over successfully');

            return redirect()->route('counters.index');
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.counters.handover');
    }
}
