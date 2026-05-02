<?php

namespace App\Livewire\Mfa;

use App\Livewire\BaseComponent;
use Illuminate\View\View;

class TrustedDevices extends BaseComponent
{
    public function render(): View
    {
        return view('livewire.mfa.trusted-devices');
    }
}
