<?php

namespace Tests\Feature\Infrastructure;

use Tests\TestCase;

class RedisPasswordTest extends TestCase
{
    /**
     * Test that REDIS_PASSWORD is configured with a strong random string.
     */
    public function test_redis_password_is_set(): void
    {
        $envFile = base_path('.env');
        $this->assertFileExists($envFile);

        $content = file_get_contents($envFile);
        \preg_match('/^REDIS_PASSWORD=(.+)$/m', $content, $matches);
        $password = $matches[1] ?? null;

        $this->assertNotNull($password, 'REDIS_PASSWORD should be set');
        $this->assertGreaterThanOrEqual(16, strlen($password), 'Redis password should be at least 16 characters');
    }
}
