<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Log;

class EncryptionService
{
    protected string $key;

    public function __construct()
    {
        $rawKey = config('app.key');
        if (empty($rawKey)) {
            throw new \RuntimeException('Encryption key not configured');
        }

        // Use PBKDF2 for secure key derivation with proper salt and iteration count
        $salt = config('app.encryption_salt');

        // SECURITY: If no encryption salt is configured, generate a random one.
        // The hardcoded default 'cems-default-salt' is NOT used because it is a
        // predictable, low-entropy value that weakens PBKDF2 derivation. Using
        // a per-installation random salt ensures unique keys even with the same
        // APP_KEY, protecting against pre-computed rainbow table attacks.
        //
        // NOTE: Auto-generated salt changes on every app restart, which means
        // previously encrypted data cannot be decrypted after restart until the
        // salt is persisted in .env as APP_ENCRYPTION_SALT. This is intentional:
        // it ensures operators configure a proper salt rather than silently
        // relying on a weak default.
        if (empty($salt)) {
            $salt = bin2hex(random_bytes(32));
            Log::critical('APP_ENCRYPTION_SALT is not configured! Generated a random salt, but previously encrypted data WILL NOT be decryptable after restart. Set APP_ENCRYPTION_SALT in .env to a 64-character hex string to ensure consistent key derivation across restarts.');
        }

        $iterations = config('app.encryption_iterations', 100000);

        // Derive a proper 32-byte key using PBKDF2 (AES-256-CBC requires 32 bytes)
        $this->key = hash_pbkdf2('sha256', $rawKey, $salt, $iterations, 32, true);
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

        return base64_encode($iv.$ciphertext);
    }

    public function decrypt(string $encryptedData): ?string
    {
        $data = base64_decode($encryptedData);
        if ($data === false || strlen($data) < 17) {
            return null;
        }
        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
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
}
