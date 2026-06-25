<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

class EncryptionKeyTest extends TestCase
{
    /**
     * Test that ENCRYPTION_KEY is set and is a 32+ character string.
     *
     * This key is used for PII field encryption and must be a strong random value.
     */
    public function test_encryption_key_is_set(): void
    {
        $envFile = base_path('.env');
        $this->assertFileExists($envFile, '.env file should exist');

        $content = file_get_contents($envFile);
        \preg_match('/^ENCRYPTION_KEY=(.+)$/m', $content, $matches);

        $key = $matches[1] ?? null;
        $this->assertNotEmpty($key, 'ENCRYPTION_KEY should be set in .env');
        $this->assertGreaterThanOrEqual(32, strlen($key), 'ENCRYPTION_KEY should be at least 32 characters');
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $key, 'ENCRYPTION_KEY should be alphanumeric');
    }
}
