<?php

namespace App\Http\Controllers;

use App\Http\Requests\Mfa\DisableMfaRequest;
use App\Http\Requests\Mfa\SetupMfaRequest;
use App\Http\Requests\Mfa\VerifyMfaRequest;
use App\Http\Requests\Mfa\VerifyRecoveryCodeRequest;
use App\Services\AuditService;
use App\Services\MfaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class MfaController extends Controller
{
    public function __construct(
        protected MfaService $mfaService,
        protected AuditService $auditService
    ) {}

    /**
     * Show the MFA setup page.
     *
     * Users who already have MFA enabled are redirected to the verification
     * page. A new TOTP secret is generated and stored temporarily in the
     * session until the initial code is verified.
     */
    public function setup(): View|RedirectResponse
    {
        $user = auth()->user();

        if ($user->mfa_enabled) {
            return redirect()->route('mfa.verify');
        }

        $secretData = $this->mfaService->generateSecret();

        Session::put('mfa_pending_secret', $secretData['secret']);
        Session::put('mfa_setup_started_at', now()->timestamp);

        return view('pages.mfa.setup', [
            'secret' => $secretData['secret'],
            'otpauthUrl' => $secretData['otpauth_url'],
            'issuer' => config('cems.mfa.issuer', 'CEMS-MY'),
        ]);
    }

    /**
     * Process MFA setup and enable MFA after verifying the initial code.
     */
    public function setupStore(SetupMfaRequest $request): View|RedirectResponse
    {
        $validated = $request->validated();

        $user = auth()->user();
        $pendingSecret = Session::pull('mfa_pending_secret');

        if (! $pendingSecret) {
            return redirect()->route('mfa.setup')
                ->withErrors(['code' => 'Session expired. Please start MFA setup again.']);
        }

        if (! $this->mfaService->verifyCode($pendingSecret, $validated['code'])) {
            Session::forget('mfa_setup_started_at');

            return redirect()->route('mfa.setup')
                ->withErrors(['code' => 'Invalid verification code. Please try again.']);
        }

        $this->mfaService->storeSecret($user, $pendingSecret);

        $recoveryCodes = $this->mfaService->generateRecoveryCodes($user);

        $this->mfaService->enableMfa($user);

        $this->auditService->logMfaEvent('mfa_setup_completed', $user->id, [
            'new' => ['method' => 'totp'],
        ]);

        Session::forget('mfa_setup_started_at');

        return view('pages.mfa.recovery-codes', [
            'recoveryCodes' => $recoveryCodes,
        ]);
    }

    /**
     * Show the MFA recovery codes page.
     *
     * Recovery codes are only displayed immediately after setup. Users arriving
     * without codes in the session are redirected to the setup page.
     */
    public function recoveryCodes(): View|RedirectResponse
    {
        $recoveryCodes = session('mfa_recovery_codes');

        if (empty($recoveryCodes) || ! is_array($recoveryCodes)) {
            return redirect()->route('mfa.setup');
        }

        return view('pages.mfa.recovery-codes', [
            'recoveryCodes' => $recoveryCodes,
        ]);
    }

    /**
     * Show the MFA verification page.
     *
     * Users without MFA enabled are redirected to setup. Already-verified
     * sessions and trusted devices are redirected to the intended destination.
     */
    public function verify(Request $request): View|RedirectResponse
    {
        $user = auth()->user();

        if (! $user->mfa_enabled) {
            return redirect()->route('mfa.setup');
        }

        if ($request->session()->get('mfa_verified', false)) {
            return redirect()->intended('/dashboard');
        }

        $fingerprint = $this->mfaService->generateDeviceFingerprint();
        if ($this->mfaService->hasTrustedDevice($user, $fingerprint)) {
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
     *
     * Accepts either a valid TOTP code or a recovery code. When the user opts
     * to remember the device, a trusted-device record is created.
     */
    public function verifyStore(VerifyMfaRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = auth()->user();
        $secret = $this->mfaService->getSecret($user);

        if (! $secret) {
            return redirect()->route('mfa.setup')
                ->withErrors(['code' => 'MFA secret not found. Please set up MFA again.']);
        }

        $valid = $this->mfaService->verifyCode($secret, $validated['code']);

        if (! $valid) {
            $valid = $this->mfaService->verifyRecoveryCode($user, $validated['code']);
        }

        if (! $valid) {
            $this->auditService->logMfaEvent('mfa_verification_failed', $user->id, [
                'new' => ['reason' => 'invalid_code'],
            ]);

            return back()->withErrors(['code' => 'Invalid code. Please try again.']);
        }

        $request->session()->put('mfa_verified', true);
        $request->session()->put('mfa_verified_at', now()->timestamp);

        $this->auditService->logMfaEvent('mfa_verification_success', $user->id);

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
     * Disable MFA after verifying the current TOTP code or recovery code.
     *
     * All trusted devices are removed and the MFA session is cleared.
     */
    public function disable(DisableMfaRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = auth()->user();
        $secret = $this->mfaService->getSecret($user);

        if (! $secret) {
            return back()->withErrors(['code' => 'MFA secret not found.']);
        }

        $valid = $this->mfaService->verifyCode($secret, $validated['code']);

        if (! $valid) {
            $valid = $this->mfaService->verifyRecoveryCode($user, $validated['code']);
        }

        if (! $valid) {
            return back()->withErrors(['code' => 'Invalid code. Cannot disable MFA.']);
        }

        $this->mfaService->removeAllTrustedDevices($user);

        $this->mfaService->disableMfa($user);

        $this->auditService->logMfaEvent('mfa_disable_completed', $user->id);

        $request->session()->forget('mfa_verified');
        $request->session()->forget('mfa_verified_at');

        return redirect('/dashboard')
            ->with('status', 'MFA has been disabled successfully.');
    }

    /**
     * Show the trusted devices management page.
     */
    public function trustedDevices(): View
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
    public function removeDevice(Request $request, int $deviceId): RedirectResponse
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
     * Show the recovery code entry page.
     */
    public function recovery(): View
    {
        return view('mfa.recovery');
    }

    /**
     * Verify a recovery code and password to grant access.
     */
    public function recoveryVerify(VerifyRecoveryCodeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = auth()->user();

        if (! $user || ! password_verify($validated['password'], $user->password_hash)) {
            return back()->withErrors(['password' => 'Invalid password.']);
        }

        if (! $this->mfaService->verifyRecoveryCode($user, $validated['recovery_code'])) {
            $this->auditService->logMfaEvent('mfa_recovery_failed', $user->id);

            return back()->withErrors(['recovery_code' => 'Invalid recovery code.']);
        }

        $request->session()->put('mfa_verified', true);
        $request->session()->put('mfa_verified_at', now()->timestamp);

        $this->auditService->logMfaEvent('mfa_recovery_success', $user->id);

        return redirect()->intended('/dashboard')
            ->with('status', 'Access recovered successfully.');
    }
}
