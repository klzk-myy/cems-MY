<?php

namespace App\Services\System;

use App\Exceptions\Domain\EncryptionConfigurationException;

class EncryptionService
{
    /**
     * Payload prefix marking the authenticated format (iv + ciphertext + MAC).
     * Payloads without it are legacy CBC-without-MAC values; decrypt() still
     * reads them so existing rows keep working until re-encrypted.
     */
    protected const FORMAT_V2_PREFIX = 'v2:';

    protected string $key;

    protected string $macKey;

    /**
     * Optional credential overrides allow instantiating the service with
     * previous APP_KEY / APP_ENCRYPTION_SALT values during key/salt rotation
     * (see customers:re-encrypt). When omitted, config values are used.
     */
    public function __construct(
        protected ?string $keyOverride = null,
        protected ?string $saltOverride = null,
    ) {
        $rawKey = $this->keyOverride ?? config('app.key');
        if (empty($rawKey)) {
            throw new EncryptionConfigurationException('Encryption key not configured');
        }

        // Use PBKDF2 for secure key derivation with proper salt and iteration count
        $salt = $this->saltOverride ?? config('app.encryption_salt');

        if (empty($salt)) {
            throw new EncryptionConfigurationException('APP_ENCRYPTION_SALT is not configured. Set it to a 64-character hex string in .env to ensure encrypted data remains decryptable across restarts.');
        }

        $iterations = config('app.encryption_iterations', 100000);

        // Derive a proper 32-byte key using PBKDF2 (AES-256-CBC requires 32 bytes)
        $this->key = hash_pbkdf2('sha256', $rawKey, $salt, $iterations, 32, true);

        // Separate MAC subkey via HKDF so the encryption key is never reused
        // for authentication (key separation).
        $this->macKey = hash_hkdf('sha256', $this->key, 32, 'encryption-mac');
    }

    public function encrypt(string $data): string
    {
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt(
            $data,
            'AES-256-CBC',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        // Encrypt-then-MAC over iv + ciphertext prevents tampering with the
        // otherwise malleable CBC payload.
        $mac = hash_hmac('sha256', $iv.$ciphertext, $this->macKey, true);

        return self::FORMAT_V2_PREFIX.base64_encode($iv.$ciphertext.$mac);
    }

    public function decrypt(string $encryptedData): ?string
    {
        if (str_starts_with($encryptedData, self::FORMAT_V2_PREFIX)) {
            return $this->decryptV2(substr($encryptedData, strlen(self::FORMAT_V2_PREFIX)));
        }

        return $this->decryptLegacy($encryptedData);
    }

    /**
     * Whether the stored payload predates the MAC'd format and should be
     * re-encrypted (customers:re-encrypt upgrades these in place).
     */
    public function isLegacyFormat(string $encryptedData): bool
    {
        return ! str_starts_with($encryptedData, self::FORMAT_V2_PREFIX);
    }

    protected function decryptV2(string $payload): ?string
    {
        $data = base64_decode($payload, true);
        // iv (16) + at least one ciphertext block (16) + mac (32)
        if ($data === false || strlen($data) < 64) {
            return null;
        }

        $mac = substr($data, -32);
        $ivCiphertext = substr($data, 0, -32);

        if (! hash_equals(hash_hmac('sha256', $ivCiphertext, $this->macKey, true), $mac)) {
            return null;
        }

        return $this->decryptRaw(substr($ivCiphertext, 16), substr($ivCiphertext, 0, 16));
    }

    /**
     * Legacy format: base64(iv + ciphertext) with no integrity protection.
     */
    protected function decryptLegacy(string $encryptedData): ?string
    {
        $data = base64_decode($encryptedData, true);
        if ($data === false || strlen($data) < 17) {
            return null;
        }

        return $this->decryptRaw(substr($data, 16), substr($data, 0, 16));
    }

    protected function decryptRaw(string $ciphertext, string $iv): ?string
    {
        $result = openssl_decrypt(
            $ciphertext,
            'AES-256-CBC',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $result !== false ? $result : null;
    }

    /**
     * Hash data using HMAC-SHA256 to prevent length extension attacks.
     *
     * @param  string  $data  Data to hash
     * @return string HMAC-SHA256 hash as hex string
     */
    public function hash(string $data): string
    {
        return hash_hmac('sha256', $data, $this->key);
    }

    /**
     * Static method for blind index computation using the same key derivation.
     *
     * PBKDF2 with 100k iterations is expensive (~50ms+ per call), and this is
     * invoked once per PII attribute during screening/search loops, so the
     * derived key is memoised for the lifetime of the process.
     *
     * @param  string  $data  Data to hash
     * @return string HMAC-SHA256 hash as hex string
     */
    public static function blindIndex(string $data): string
    {
        static $derivedKey = null;

        if ($derivedKey === null) {
            $rawKey = config('app.key');
            if (empty($rawKey)) {
                throw new EncryptionConfigurationException('Encryption key not configured');
            }

            $salt = config('app.encryption_salt');
            if (empty($salt)) {
                throw new EncryptionConfigurationException('APP_ENCRYPTION_SALT is not configured');
            }

            $iterations = config('app.encryption_iterations', 100000);
            $derivedKey = hash_pbkdf2('sha256', $rawKey, $salt, $iterations, 32, true);
        }

        return hash_hmac('sha256', $data, $derivedKey);
    }
}
