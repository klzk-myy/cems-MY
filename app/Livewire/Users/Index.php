<?php

namespace App\Livewire\Users;

use App\Livewire\BaseComponent;
use App\Models\User;
use App\Services\UserService;
use Illuminate\View\View;
use Livewire\WithPagination;

class Index extends BaseComponent
{
    use WithPagination;

    public string $search = '';

    public string $roleFilter = '';

    public string $statusFilter = '';

    public function mount(): void {}

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function toggleActive(User $user): void
    {
        try {
            $userService = app(UserService::class);
            $user = $userService->toggleActive($user, auth()->id());
            $status = $user->is_active ? 'activated' : 'deactivated';
            $this->success("User {$user->username} has been {$status}!");
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
        }
    }

    public function render(): View
    {
        $query = User::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('username', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->roleFilter) {
            $query->where('role', $this->roleFilter);
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('livewire.users.index', compact('users'));
    }
}
