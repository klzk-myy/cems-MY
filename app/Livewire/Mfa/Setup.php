<?php

namespace App\Livewire\Mfa;

use App\Livewire\BaseComponent;
use App\Services\MfaService;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class Setup extends BaseComponent
{
    public string $secret = '';

    public string $otpauthUrl = '';

    public string $issuer = '';

    public string $code = '';

    public string $error = '';

    public array $recoveryCodes = [];

    public function mount(): void
    {
        $user = auth()->user();

        if ($user->mfa_enabled) {
            $this->redirect(route('mfa.verify'));

            return;
        }

        $mfaService = app(MfaService::class);
        $secretData = $mfaService->generateSecret();

        Session::put('mfa_pending_secret', $secretData['secret']);
        Session::put('mfa_setup_started_at', now()->timestamp);

        $this->secret = $secretData['secret'];
        $this->otpauthUrl = $secretData['otpauth_url'];
        $this->issuer = config('cems.mfa.issuer', 'CEMS-MY');
    }

    public function verify(): mixed
    {
        if (strlen($this->code) !== 6 || ! is_numeric($this->code)) {
            $this->error = 'Please enter a valid 6-digit code';

            return null;
        }

        $user = auth()->user();
        $pendingSecret = Session::pull('mfa_pending_secret');

        if (! $pendingSecret) {
            $this->error = 'Session expired. Please start MFA setup again.';

            return null;
        }

        $mfaService = app(MfaService::class);

        if (! $mfaService->verifyCode($pendingSecret, $this->code)) {
            Session::forget('mfa_setup_started_at');
            $this->error = 'Invalid verification code. Please try again.';

            return null;
        }

        $mfaService->storeSecret($user, $pendingSecret);
        $this->recoveryCodes = $mfaService->generateRecoveryCodes($user);
        $mfaService->enableMfa($user);

        Session::forget('mfa_setup_started_at');

        $this->success('MFA enabled successfully!');

        return $this->redirect(route('mfa.recovery'));
    }

    public function render(): View
    {
        return view('livewire.mfa.setup');
    }
}
