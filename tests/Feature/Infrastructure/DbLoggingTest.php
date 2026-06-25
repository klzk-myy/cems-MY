<?php

namespace Tests\Feature\Infrastructure;

use Tests\TestCase;

class DbLoggingTest extends TestCase
{
    /**
     * Test that database query logging is disabled in production.
     */
    public function test_db_logging_disabled(): void
    {
        $envFile = base_path('.env');
        $this->assertFileExists($envFile);

        $content = file_get_contents($envFile);
        \preg_match('/^DB_LOGGING=(.+)$/m', $content, $matches);
        $logging = trim($matches[1] ?? '');

        $this->assertSame('false', $logging, 'DB_LOGGING should be false');
    }

    /**
     * Test that query log level is set to warning.
     */
    public function test_query_log_level_is_warning(): void
    {
        $envFile = base_path('.env');
        $this->assertFileExists($envFile);

        $content = file_get_contents($envFile);
        \preg_match('/^QUERY_LOG_LEVEL=(.+)$/m', $content, $matches);
        $level = trim($matches[1] ?? '');

        $this->assertSame('warning', $level, 'QUERY_LOG_LEVEL should be warning');
    }
}
