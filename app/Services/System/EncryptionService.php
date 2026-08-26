<?php

namespace App\Services\System;

use App\Exceptions\Domain\EncryptionConfigurationException;

class EncryptionService
{
    protected string $key;

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
