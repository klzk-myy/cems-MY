<?php

namespace Tests\Feature\Infrastructure;

use Tests\TestCase;

class QueueConnectionTest extends TestCase
{
    /**
     * Test that queue connection is set to redis.
     */
    public function test_queue_connection_is_redis(): void
    {
        $envFile = base_path('.env');
        $this->assertFileExists($envFile);

        $content = file_get_contents($envFile);
        \preg_match('/^QUEUE_CONNECTION=(.+)$/m', $content, $matches);
        $connection = trim($matches[1] ?? '');

        $this->assertSame('redis', $connection, 'QUEUE_CONNECTION should be redis');
    }
}
