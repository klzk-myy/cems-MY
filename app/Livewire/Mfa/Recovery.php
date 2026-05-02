<?php

namespace App\Livewire\Mfa;

use App\Livewire\BaseComponent;
use Illuminate\View\View;

class Recovery extends BaseComponent
{
    public array $recoveryCodes = [];

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user->mfa_enabled) {
            $this->redirect(route('mfa.setup'));
        }

        // Get recovery codes from session if just set up
        $this->recoveryCodes = session('mfa_recovery_codes', []);
    }

    public function render(): View
    {
        return view('livewire.mfa.recovery');
    }
}
