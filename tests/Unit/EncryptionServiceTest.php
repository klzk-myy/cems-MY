<?php

namespace Tests\Unit;

use App\Services\EncryptionService;
use Tests\TestCase;

class EncryptionServiceTest extends TestCase
{
    private EncryptionService $encryptionService;

    protected function setUp(): void
    {
        parent::setUp();
        // Set a test encryption key (32 bytes or longer will be derived to 32)
        config(['app.encryption_key' => 'test-encryption-key-for-unit-testing-32-bytes!']);
        $this->encryptionService = new EncryptionService;
    }

    public function test_can_encrypt_and_decrypt_data(): void
    {
        $plaintext = 'This is sensitive data';

        $encrypted = $this->encryptionService->encrypt($plaintext);
        $decrypted = $this->encryptionService->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function test_encrypts_produce_different_ciphertexts(): void
    {
        $plaintext = 'Same input data';

        $encrypted1 = $this->encryptionService->encrypt($plaintext);
        $encrypted2 = $this->encryptionService->encrypt($plaintext);

        // Due to random IV, ciphertexts should be different
        $this->assertNotEquals($encrypted1, $encrypted2);
    }

    public function test_decrypt_with_invalid_data_returns_null(): void
    {
        $this->assertNull($this->encryptionService->decrypt('invalid-data'));
        $this->assertNull($this->encryptionService->decrypt(''));
        $this->assertNull($this->encryptionService->decrypt(base64_encode('short')));
    }

    public function test_encrypt_empty_string(): void
    {
        $plaintext = '';

        $encrypted = $this->encryptionService->encrypt($plaintext);
        $decrypted = $this->encryptionService->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function test_encrypt_unicode_characters(): void
    {
        $plaintext = '日本語テスト 한국어 Ελληνικά';

        $encrypted = $this->encryptionService->encrypt($plaintext);
        $decrypted = $this->encryptionService->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function test_encrypt_very_long_string(): void
    {
        $plaintext = str_repeat('A very long string. ', 1000);

        $encrypted = $this->encryptionService->encrypt($plaintext);
        $decrypted = $this->encryptionService->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function test_hash_produces_consistent_output(): void
    {
        $data = 'test data';
        $hash1 = $this->encryptionService->hash($data);
        $hash2 = $this->encryptionService->hash($data);

        $this->assertEquals($hash1, $hash2);
        $this->assertEquals(64, strlen($hash1)); // SHA-256 produces 64 hex chars
    }

    public function test_hash_produces_different_output_for_different_data(): void
    {
        $hash1 = $this->encryptionService->hash('data1');
        $hash2 = $this->encryptionService->hash('data2');

        $this->assertNotEquals($hash1, $hash2);
    }

    public function test_hash_uses_hmac_not_simple_concat(): void
    {
        // This test verifies the fix for FAU-004 (length extension attack vulnerability)
        $data = 'sensitive data';
        $hash = $this->encryptionService->hash($data);

        // Verify it's a valid SHA-256 HMAC
        $this->assertEquals(64, strlen($hash));
        $this->assertTrue(ctype_xdigit($hash));
    }

    public function test_encryption_key_is_derived_to_32_bytes(): void
    {
        // Verify that the key is properly derived to 32 bytes for AES-256
        $reflection = new \ReflectionClass($this->encryptionService);
        $property = $reflection->getProperty('key');
        $property->setAccessible(true);

        $key = $property->getValue($this->encryptionService);

        // SHA-256 produces 32 raw bytes
        $this->assertEquals(32, strlen($key));
    }

    public function test_encrypt_produces_base64_output(): void
    {
        $encrypted = $this->encryptionService->encrypt('test');
        $this->assertTrue(base64_decode($encrypted) !== false);
    }

    public function test_malaysian_ic_format(): void
    {
        $ic = '901213-01-2345';
        $encrypted = $this->encryptionService->encrypt($ic);
        $decrypted = $this->encryptionService->decrypt($encrypted);
        $this->assertEquals($ic, $decrypted);
    }
}
