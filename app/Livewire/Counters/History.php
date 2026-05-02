<?php

namespace App\Livewire\Counters;

use App\Livewire\BaseComponent;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\User;
use Illuminate\View\View;
use Livewire\WithPagination;

class History extends BaseComponent
{
    use WithPagination;

    public Counter $counter;

    public array $users = [];

    public ?string $fromDate = null;

    public ?string $toDate = null;

    public ?int $userId = null;

    protected $queryString = [
        'fromDate' => ['except' => null],
        'toDate' => ['except' => null],
        'userId' => ['except' => null],
    ];

    public function mount(Counter $counter): void
    {
        $this->counter = $counter;
        $this->users = User::where('is_active', true)->get()->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->username,
        ])->toArray();
    }

    public function render(): View
    {
        $query = CounterSession::where('counter_id', $this->counter->id)
            ->with(['user', 'openedByUser', 'closedByUser']);

        if ($this->fromDate) {
            $query->where('session_date', '>=', $this->fromDate);
        }

        if ($this->toDate) {
            $query->where('session_date', '<=', $this->toDate);
        }

        if ($this->userId) {
            $query->where('user_id', $this->userId);
        }

        $sessions = $query->orderBy('session_date', 'desc')
            ->orderBy('opened_at', 'desc')
            ->paginate(20);

        return view('livewire.counters.history', compact('sessions'));
    }

    public function clearFilters(): void
    {
        $this->fromDate = null;
        $this->toDate = null;
        $this->userId = null;
        $this->resetPage();
    }
}
