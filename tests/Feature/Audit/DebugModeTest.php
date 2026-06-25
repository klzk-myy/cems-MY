<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

class DebugModeTest extends TestCase
{
    /**
     * Test that APP_DEBUG is disabled in the environment configuration.
     *
     * Debug mode must be false in production to prevent information leakage.
     */
    public function test_app_debug_is_disabled(): void
    {
        $envFile = base_path('.env');
        $this->assertFileExists($envFile, '.env file should exist');

        $content = file_get_contents($envFile);
        \preg_match('/^APP_DEBUG=(.+)$/m', $content, $matches);

        $debug = $matches[1] ?? null;
        $this->assertNotNull($debug, 'APP_DEBUG should be set in .env');
        $this->assertSame('false', $debug, 'APP_DEBUG must be false to prevent stack trace leakage');
    }
}
