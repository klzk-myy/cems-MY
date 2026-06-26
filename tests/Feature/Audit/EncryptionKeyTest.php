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
        $key = config('app.encryption_key') ?? env('ENCRYPTION_KEY');

        $this->assertNotEmpty($key, 'ENCRYPTION_KEY should be configured');
        $this->assertGreaterThanOrEqual(32, strlen($key), 'ENCRYPTION_KEY should be at least 32 characters');
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_-]+$/', $key, 'ENCRYPTION_KEY should be alphanumeric');
    }
}
