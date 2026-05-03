<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\MfaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class MfaController extends Controller
{
    public function __construct(
        protected MfaService $mfaService,
        protected AuditService $auditService
    ) {}

    /**
     * Show MFA setup page.
     */
    public function setup()
    {
        $user = auth()->user();

        // If MFA already enabled, redirect to verify or dashboard
        if ($user->mfa_enabled) {
            return redirect()->route('mfa.verify');
        }

        // Generate new secret
        $secretData = $this->mfaService->generateSecret();

        // Store temporary secret for verification
        Session::put('mfa_pending_secret', $secretData['secret']);
        Session::put('mfa_setup_started_at', now()->timestamp);

        return view('pages.mfa.setup', [
            'secret' => $secretData['secret'],
            'otpauthUrl' => $secretData['otpauth_url'],
            'issuer' => config('cems.mfa.issuer', 'CEMS-MY'),
        ]);
    }

    /**
     * Process MFA setup - verify initial code and enable MFA.
     */
    public function setupStore(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:6',
        ]);

        $user = auth()->user();
        $pendingSecret = Session::pull('mfa_pending_secret');

        if (! $pendingSecret) {
            return redirect()->route('mfa.setup')
                ->withErrors(['code' => 'Session expired. Please start MFA setup again.']);
        }

        // Verify the code
        if (! $this->mfaService->verifyCode($pendingSecret, $request->code)) {
            // Re-generate secret and start over
            Session::forget('mfa_setup_started_at');

            return redirect()->route('mfa.setup')
                ->withErrors(['code' => 'Invalid verification code. Please try again.']);
        }

        // Store the secret (encrypted)
        $this->mfaService->storeSecret($user, $pendingSecret);

        // Generate recovery codes
        $recoveryCodes = $this->mfaService->generateRecoveryCodes($user);

        // Enable MFA
        $this->mfaService->enableMfa($user);

        $this->auditService->logMfaEvent('mfa_setup_completed', $user->id, [
            'new' => ['method' => 'totp'],
        ]);

        // Clear setup session
        Session::forget('mfa_setup_started_at');

        // Show recovery codes (only time they're displayed)
        return view('pages.mfa.recovery-codes', [
            'recoveryCodes' => $recoveryCodes,
        ]);
    }

    /**
     * Show MFA verification page.
     */
    public function verify(Request $request)
    {
        $user = auth()->user();

        // If MFA not enabled, redirect to setup
        if (! $user->mfa_enabled) {
            return redirect()->route('mfa.setup');
        }

        // Check if already verified in this session
        if ($request->session()->get('mfa_verified', false)) {
            return redirect()->intended('/dashboard');
        }

        // Check for trusted device
        $fingerprint = $this->mfaService->generateDeviceFingerprint();
        if ($this->mfaService->hasTrustedDevice($user, $fingerprint)) {
            // Mark session as verified and redirect
            $request->session()->put('mfa_verified', true);
            $request->session()->put('mfa_verified_at', now()->timestamp);

            return redirect()->intended('/dashboard');
        }

        return view('pages.mfa.verify', [
            'rememberDevice' => true,
        ]);
    }

    /**
     * Process MFA verification.
     */
    public function verifyStore(Request $request)
    {
        $request->validate([
            'code' => 'required|string|min:6|max:10', // 6 for TOTP, 10 for recovery code
        ]);

        $user = auth()->user();
        $secret = $this->mfaService->getSecret($user);

        if (! $secret) {
            return redirect()->route('mfa.setup')
                ->withErrors(['code' => 'MFA secret not found. Please set up MFA again.']);
        }

        // Try TOTP code first
        $valid = $this->mfaService->verifyCode($secret, $request->code);

        // If invalid, try recovery code
        if (! $valid) {
            $valid = $this->mfaService->verifyRecoveryCode($user, $request->code);
        }

        if (! $valid) {
            $this->auditService->logMfaEvent('mfa_verification_failed', $user->id, [
                'new' => ['reason' => 'invalid_code'],
            ]);

            return back()->withErrors(['code' => 'Invalid code. Please try again.']);
        }

        // Mark session as verified
        $request->session()->put('mfa_verified', true);
        $request->session()->put('mfa_verified_at', now()->timestamp);

        $this->auditService->logMfaEvent('mfa_verification_success', $user->id);

        // Remember device if checkbox checked
        if ($request->boolean('remember_device')) {
            $fingerprint = $this->mfaService->generateDeviceFingerprint();
            $days = config('cems.mfa.remember_days', 30);
            $this->mfaService->rememberDevice(
                $user,
                $fingerprint,
                $request->userAgent(),
                $days
            );
        }

        return redirect()->intended('/dashboard');
    }

    /**
     * Disable MFA (requires current verification).
     */
    public function disable(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:6',
        ]);

        $user = auth()->user();
        $secret = $this->mfaService->getSecret($user);

        if (! $secret) {
            return back()->withErrors(['code' => 'MFA secret not found.']);
        }

        // Verify before disabling
        $valid = $this->mfaService->verifyCode($secret, $request->code);

        if (! $valid) {
            $valid = $this->mfaService->verifyRecoveryCode($user, $request->code);
        }

        if (! $valid) {
            return back()->withErrors(['code' => 'Invalid code. Cannot disable MFA.']);
        }

        // Remove all trusted devices
        $this->mfaService->removeAllTrustedDevices($user);

        // Disable MFA
        $this->mfaService->disableMfa($user);

        $this->auditService->logMfaEvent('mfa_disable_completed', $user->id);

        // Clear MFA session
        $request->session()->forget('mfa_verified');
        $request->session()->forget('mfa_verified_at');

        return redirect('/dashboard')
            ->with('status', 'MFA has been disabled successfully.');
    }

    /**
     * Show trusted devices management page.
     */
    public function trustedDevices()
    {
        $user = auth()->user();
        $devices = $this->mfaService->getTrustedDevices($user);

        return view('mfa.trusted-devices', [
            'devices' => $devices,
        ]);
    }

    /**
     * Remove a trusted device.
     */
    public function removeDevice(Request $request, $deviceId)
    {
        $user = auth()->user();

        if ($this->mfaService->removeTrustedDevice($user, $deviceId)) {
            $this->auditService->logMfaEvent('mfa_trusted_device_removed', $user->id, [
                'new' => ['device_id' => $deviceId],
            ]);

            return redirect()->back()
                ->with('status', 'Device removed successfully.');
        }

        return redirect()->back()
            ->withErrors(['device' => 'Device not found.']);
    }

    /**
     * Show recovery code entry page.
     */
    public function recovery()
    {
        return view('mfa.recovery');
    }
}
