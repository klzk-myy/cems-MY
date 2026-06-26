<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

class AppKeyTest extends TestCase
{
    /**
     * Test that APP_KEY is set and is a 32-byte base64-encoded key.
     *
     * This key is the foundation for Laravel encryption and PII field encryption.
     */
    public function test_app_key_is_set(): void
    {
        $key = config('app.key');

        $this->assertNotEmpty($key, 'APP_KEY should be configured');
        $this->assertMatchesRegularExpression('/^base64:[a-zA-Z0-9+\/]+={0,2}$/', $key, 'APP_KEY should be a base64-encoded key');

        $rawKey = base64_decode(substr($key, 7), true);
        $this->assertNotFalse($rawKey, 'APP_KEY should be valid base64');
        $this->assertSame(32, strlen($rawKey), 'APP_KEY should decode to 32 bytes');
    }
}
