<?php

namespace App\Livewire\Mfa;

use App\Livewire\BaseComponent;
use App\Services\MfaService;
use Illuminate\View\View;

class Verify extends BaseComponent
{
    public string $code = '';

    public string $error = '';

    public bool $remember = false;

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user->mfa_enabled) {
            $this->redirect(route('mfa.setup'));
        }
    }

    public function verify(): mixed
    {
        if (strlen($this->code) !== 6 || ! is_numeric($this->code)) {
            $this->error = 'Please enter a valid 6-digit code';

            return null;
        }

        $user = auth()->user();
        $mfaService = app(MfaService::class);

        if (! $mfaService->verifyCode($user->mfa_secret, $this->code)) {
            $this->error = 'Invalid verification code';

            return null;
        }

        session(['mfa_verified' => true]);

        if ($this->remember) {
            cookie()->queue('mfa_trusted', encrypt(auth()->id()), 60 * 24 * 30);
        }

        $this->success('Verification successful!');

        return redirect()->intended('/dashboard');
    }

    public function render(): View
    {
        return view('livewire.mfa.verify');
    }
}
