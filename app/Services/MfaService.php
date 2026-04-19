<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\DeviceComputations;
use App\Models\MfaRecoveryCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * MfaService - TOTP-based Multi-Factor Authentication
 *
 * Implements RFC 6238 TOTP algorithm for authenticator app compatibility
 * with Google Authenticator, Authy, and similar applications.
 */
class MfaService
{
    private int $period;

    private int $digits;

    private string $issuer;

    public function __construct(
        protected AuditService $auditService,
    ) {
        $this->period = config('cems.mfa.period', 30);
        $this->digits = config('cems.mfa.digits', 6);
        $this->issuer = config('cems.mfa.issuer', 'CEMS-MY');
    }

    /**
     * Generate a new TOTP secret for a user.
     *
     * @return array{secret: string, otpauth_url: string}
     */
    public function generateSecret(?string $accountName = null): array
    {
        // Generate 20 bytes of random data and base32 encode
        $secret = $this->base32Encode(random_bytes(20));

        // Build otpauth URL for QR code
        $otpauthUrl = $this->buildOtpauthUrl($secret, $accountName);

        return [
            'secret' => $secret,
            'otpauth_url' => $otpauthUrl,
        ];
    }

    /**
     * Build the otpauth:// URL for QR code generation.
     */
    public function buildOtpauthUrl(string $secret, ?string $accountName = null): string
    {
        // Use provided account name or get from auth
        $label = $accountName ?? auth()->user()->email ?? 'user@example.com';
        $label = rawurlencode($label);

        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $this->issuer,
            'period' => $this->period,
            'digits' => $this->digits,
            'algorithm' => 'SHA1',
        ]);

        return "otpauth://totp/{$label}?{$params}";
    }

    /**
     * Verify a TOTP code against a user's secret.
     */
    public function verifyCode(string $secret, string $code): bool
    {
        $secret = strtoupper($secret);
        $code = trim($code);

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        // Check current and previous time windows (allow 1 step tolerance)
        $currentTime = time();

        for ($offset = -1; $offset <= 1; $offset++) {
            $expectedCode = $this->generateCode($secret, $currentTime + ($offset * $this->period));
            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a TOTP code for a given time.
     */
    public function generateCode(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();

        // Pack timestamp into 8 bytes (big-endian)
        $time = pack('J', $timestamp / $this->period);

        // Base32 decode the secret
        $secretDecoded = $this->base32Decode($secret);

        // Generate HMAC-SHA1
        $hash = hash_hmac('sha1', $time, $secretDecoded, true);

        // Dynamic truncation
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        // Generate OTPCode
        $otp = $binary % pow(10, $this->digits);

        return str_pad((string) $otp, $this->digits, '0', STR_PAD_LEFT);
    }

    /**
     * Encrypt and store the TOTP secret for a user.
     */
    public function storeSecret(User $user, string $secret): void
    {
        $user->mfa_secret = Crypt::encryptString($secret);
        $user->save();
    }

    /**
     * Get the decrypted TOTP secret for a user.
     */
    public function getSecret(User $user): ?string
    {
        if (empty($user->mfa_secret)) {
            return null;
        }

        try {
            return Crypt::decryptString($user->mfa_secret);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate recovery codes for a user.
     *
     * @return array<string> Plain text codes (shown only once)
     */
    public function generateRecoveryCodes(User $user): array
    {
        $codes = [];

        for ($i = 0; $i < 10; $i++) {
            // Generate 10 random recovery codes
            $code = strtoupper(Str::random(4).'-'.Str::random(4));
            $codes[] = $code;

            // Hash and store
            MfaRecoveryCode::create([
                'user_id' => $user->id,
                'code_hash' => Hash::make($code),
                'used' => false,
            ]);
        }

        return $codes;
    }

    /**
     * Verify a recovery code.
     */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $code = strtoupper(trim($code));

        $recoveryCodes = MfaRecoveryCode::where('user_id', $user->id)
            ->where('used', false)
            ->get();

        foreach ($recoveryCodes as $recoveryCode) {
            if (Hash::check($code, $recoveryCode->code_hash)) {
                // Mark as used
                $recoveryCode->update([
                    'used' => true,
                    'used_at' => now(),
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * Get remaining recovery codes count.
     */
    public function getRemainingRecoveryCodesCount(User $user): int
    {
        return MfaRecoveryCode::where('user_id', $user->id)
            ->where('used', false)
            ->count();
    }

    /**
     * Enable MFA for a user.
     */
    public function enableMfa(User $user): void
    {
        $user->mfa_enabled = true;
        $user->mfa_verified_at = now();
        $user->save();

        // Log the change
        $this->auditService->logWithSeverity(
            'mfa_enabled',
            [
                'user_id' => $user->id,
                'entity_type' => 'User',
                'entity_id' => $user->id,
            ],
            'WARNING'
        );
    }

    /**
     * Disable MFA for a user.
     */
    public function disableMfa(User $user): void
    {
        $oldSecret = $user->mfa_secret;

        $user->mfa_enabled = false;
        $user->mfa_secret = null;
        $user->mfa_verified_at = null;
        $user->save();

        // Delete recovery codes
        MfaRecoveryCode::where('user_id', $user->id)->delete();

        // Log the change
        $this->auditService->logWithSeverity(
            'mfa_disabled',
            [
                'user_id' => $user->id,
                'entity_type' => 'User',
                'entity_id' => $user->id,
                'old_values' => ['mfa_secret' => 'exists'],
                'new_values' => ['mfa_secret' => null],
            ],
            'WARNING'
        );
    }

    /**
     * Check if a user has a trusted device.
     */
    public function hasTrustedDevice(User $user, string $fingerprint): bool
    {
        $trustedDevice = DeviceComputations::where('user_id', $user->id)
            ->where('device_fingerprint', $fingerprint)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($trustedDevice) {
            // Update last used
            $trustedDevice->update(['last_used_at' => now()]);

            return true;
        }

        return false;
    }

    /**
     * Remember a device for MFA bypass.
     */
    public function rememberDevice(User $user, string $fingerprint, ?string $deviceName = null, ?int $days = null): void
    {
        $days = $days ?? config('cems.mfa.remember_days', 30);

        DeviceComputations::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_fingerprint' => $fingerprint,
            ],
            [
                'device_name' => $deviceName,
                'ip_address' => request()->ip(),
                'expires_at' => now()->addDays($days),
                'last_used_at' => now(),
            ]
        );
    }

    /**
     * Remove all trusted devices for a user.
     */
    public function removeAllTrustedDevices(User $user): void
    {
        DeviceComputations::where('user_id', $user->id)->delete();
    }

    /**
     * Remove a specific trusted device.
     */
    public function removeTrustedDevice(User $user, int $deviceId): bool
    {
        return DeviceComputations::where('user_id', $user->id)
            ->where('id', $deviceId)
            ->delete() > 0;
    }

    /**
     * Get all trusted devices for a user.
     */
    public function getTrustedDevices(User $user): Collection
    {
        return DeviceComputations::where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('last_used_at', 'desc')
            ->get();
    }

    /**
     * Check if MFA is required for a user based on their role.
     */
    public function isMfaRequiredForRole(User $user): bool
    {
        $requireForRoles = config('cems.mfa.require_for_roles', []);

        if (empty($requireForRoles)) {
            return false;
        }

        $roleName = match ($user->role) {
            UserRole::Admin => 'admin',
            UserRole::Manager => 'manager',
            UserRole::ComplianceOfficer => 'compliance',
            UserRole::Teller => 'teller',
        };

        return in_array($roleName, $requireForRoles);
    }

    /**
     * Check if MFA is enabled and configured globally.
     */
    public function isGloballyEnabled(): bool
    {
        return config('cems.mfa.enabled', true);
    }

    /**
     * Generate a device fingerprint based on request data.
     */
    public function generateDeviceFingerprint(): string
    {
        $data = implode('|', [
            request()->userAgent() ?? 'unknown',
            request()->ip() ?? '0.0.0.0',
            request()->header('Accept-Language') ?? 'en',
        ]);

        return hash('sha256', $data);
    }

    /**
     * Base32 encode data.
     */
    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        $output = '';

        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        // Pad to multiple of 5
        $binary = str_pad($binary, ceil(strlen($binary) / 5) * 5, '0', STR_PAD_RIGHT);

        // Split into 5-bit groups
        foreach (str_split($binary, 5) as $chunk) {
            $index = bindec($chunk);
            $output .= $alphabet[$index];
        }

        // Pad with '=' to make output multiple of 8
        $output = str_pad($output, ceil(strlen($output) / 8) * 8, '=', STR_PAD_RIGHT);

        return $output;
    }

    /**
     * Base32 decode data.
     */
    private function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = strtoupper(str_replace(['=', ' '], '', $data));
        $binary = '';

        foreach (str_split($data) as $char) {
            $index = strpos($alphabet, $char);
            if ($index === false) {
                continue;
            }
            $binary .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        // Pad to multiple of 8
        $binary = str_pad($binary, ceil(strlen($binary) / 8) * 8, '0', STR_PAD_RIGHT);

        // Split into 8-bit groups
        $output = '';
        foreach (str_split($binary, 8) as $chunk) {
            $output .= chr(bindec($chunk));
        }

        return $output;
    }
}
