<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Mfa\ConfirmMfaEnrollmentRequest;
use App\Http\Requests\Api\V1\Mfa\DisableMfaRequest;
use App\Http\Requests\Api\V1\Mfa\EnrollMfaRequest;
use App\Http\Requests\Api\V1\Mfa\RegenerateRecoveryCodesRequest;
use App\Models\MfaRecoveryCode;
use App\Services\AuditService;
use App\Services\System\MfaService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * MFA lifecycle endpoints for API (Sanctum) clients.
 *
 * Mirrors the web enrollment flow in App\Http\Controllers\MfaController via
 * MfaService, with the pending secret held in the cache keyed by user id
 * instead of the session so it survives stateless token requests.
 */
class MfaController extends Controller
{
    use ApiResponse;

    private const PENDING_SECRET_PREFIX = 'mfa_api_pending_secret:';

    public function __construct(
        protected MfaService $mfaService,
        protected AuditService $auditService
    ) {}

    /**
     * Start TOTP enrollment: returns the secret, otpauth URI and a QR code
     * data URI. Requires the current password.
     */
    public function enroll(EnrollMfaRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->mfa_enabled) {
            return $this->errorResponse('MFA is already enabled for this account.', [], 400);
        }

        $secretData = $this->mfaService->generateSecret($user->email);

        Cache::store(config('ratelimit.store'))->put(
            self::PENDING_SECRET_PREFIX.$user->id,
            $secretData['secret'],
            now()->addMinutes(15)
        );

        $this->auditService->logMfaEvent('mfa_enrollment_started', $user->id, [
            'new' => ['channel' => 'api'],
        ]);

        return $this->successResponse([
            'secret' => $secretData['secret'],
            'otpauth_url' => $secretData['otpauth_url'],
            'qr_code_data_uri' => $this->qrCodeDataUri($secretData['otpauth_url']),
        ], 'Scan the QR code with your authenticator app, then confirm with POST api/v1/mfa/verify.');
    }

    /**
     * Confirm enrollment by verifying the first TOTP code against the pending
     * secret; activates MFA and returns recovery codes once.
     */
    public function verify(ConfirmMfaEnrollmentRequest $request): JsonResponse
    {
        $user = $request->user();

        // Keep the pending secret across failed attempts so the user can
        // retry without re-enrolling; it is dropped on success.
        $pendingSecret = Cache::store(config('ratelimit.store'))
            ->get(self::PENDING_SECRET_PREFIX.$user->id);

        if (! is_string($pendingSecret) || $pendingSecret === '') {
            return $this->errorResponse('No pending MFA enrollment. Call POST api/v1/mfa/enroll first.', [], 400);
        }

        // Same brute-force lockout as web verification (5 failures / 15 min).
        if ($this->mfaService->hasTooManyFailedAttempts($user)) {
            $this->auditService->logMfaEvent('mfa_verification_locked', $user->id);

            return $this->errorResponse('Too many failed attempts. Please try again in 15 minutes.', [], 429);
        }

        if (! $this->mfaService->verifyCode($pendingSecret, $request->validated('code'))) {
            $this->mfaService->recordFailedAttempt($user);

            $this->auditService->logMfaEvent('mfa_verification_failed', $user->id, [
                'new' => ['reason' => 'invalid_code', 'context' => 'api_enrollment'],
            ]);

            return $this->errorResponse('Invalid verification code.', ['code' => ['Invalid verification code.']], 422);
        }

        $this->mfaService->clearFailedAttempts($user);

        Cache::store(config('ratelimit.store'))->forget(self::PENDING_SECRET_PREFIX.$user->id);

        $this->mfaService->storeSecret($user, $pendingSecret);

        $recoveryCodes = $this->mfaService->generateRecoveryCodes($user);

        $this->mfaService->enableMfa($user);

        $this->auditService->logMfaEvent('mfa_setup_completed', $user->id, [
            'new' => ['method' => 'totp', 'channel' => 'api'],
        ]);

        return $this->successResponse([
            'recovery_codes' => $recoveryCodes,
        ], 'MFA enabled. Store the recovery codes securely; they are shown only once.');
    }

    /**
     * Invalidate all existing recovery codes and issue a fresh set, shown once.
     * Requires the current password.
     */
    public function regenerateRecoveryCodes(RegenerateRecoveryCodesRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->mfa_enabled) {
            return $this->errorResponse('MFA is not enabled for this account.', [], 400);
        }

        MfaRecoveryCode::where('user_id', $user->id)->delete();

        $recoveryCodes = $this->mfaService->generateRecoveryCodes($user);

        $this->auditService->logMfaEvent('mfa_recovery_codes_regenerated', $user->id, [
            'new' => ['channel' => 'api'],
        ]);

        return $this->successResponse([
            'recovery_codes' => $recoveryCodes,
        ], 'New recovery codes generated. Previous codes are no longer valid.');
    }

    /**
     * Disable MFA: requires both the current password and a valid TOTP or
     * recovery code. Removes all trusted devices and clears MFA state.
     */
    public function disable(DisableMfaRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();

        if ($this->mfaService->hasTooManyFailedAttempts($user)) {
            $this->auditService->logMfaEvent('mfa_disable_locked', $user->id);

            return $this->errorResponse('Too many failed attempts. Please try again in 15 minutes.', [], 429);
        }

        $secret = $this->mfaService->getSecret($user);

        if (! $secret) {
            return $this->errorResponse('MFA secret not found.', [], 400);
        }

        $valid = $this->mfaService->verifyCode($secret, $validated['code']);

        if (! $valid) {
            $valid = $this->mfaService->verifyRecoveryCode($user, $validated['code']);
        }

        if (! $valid) {
            $this->mfaService->recordFailedAttempt($user);

            return $this->errorResponse('Invalid code. Cannot disable MFA.', ['code' => ['Invalid code. Cannot disable MFA.']], 422);
        }

        $this->mfaService->clearFailedAttempts($user);

        $this->mfaService->removeAllTrustedDevices($user);

        $this->mfaService->disableMfa($user);

        $this->auditService->logMfaEvent('mfa_disable_completed', $user->id, [
            'new' => ['channel' => 'api'],
        ]);

        return $this->successResponse(null, 'MFA has been disabled successfully.');
    }

    /**
     * Render the otpauth URI as an inline SVG data URI.
     */
    private function qrCodeDataUri(string $otpauthUrl): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(240),
            new SvgImageBackEnd
        );

        $svg = (new Writer($renderer))->writeString($otpauthUrl);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
