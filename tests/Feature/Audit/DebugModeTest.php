<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

class DebugModeTest extends TestCase
{
    /**
     * Test that APP_DEBUG is explicitly configured.
     *
     * The exact value depends on the environment (local=true, production=false),
     * but the configuration must always be present and boolean.
     */
    public function test_app_debug_is_configured(): void
    {
        $debug = config('app.debug');

        $this->assertNotNull($debug, 'APP_DEBUG should be configured');
        $this->assertIsBool($debug, 'APP_DEBUG must be a boolean');
    }

    /**
     * Test that APP_DEBUG is disabled in production.
     */
    public function test_app_debug_is_disabled_in_production(): void
    {
        if (config('app.env') !== 'production') {
            $this->markTestSkipped('Only applies in production environment');
        }

        $this->assertFalse(config('app.debug'), 'APP_DEBUG must be false in production');
    }
}
