<?php

namespace App\Livewire\Users;

use App\Livewire\BaseComponent;
use App\Models\User;
use Illuminate\View\View;

class Show extends BaseComponent
{
    public User $user;

    public function mount(User $user): void
    {
        $this->user = $user->load(['branch', 'counters']);
    }

    public function render(): View
    {
        return view('livewire.users.show');
    }
}
