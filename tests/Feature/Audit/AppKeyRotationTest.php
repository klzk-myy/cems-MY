<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

class AppKeyRotationTest extends TestCase
{
    /**
     * Test that Laravel config has a non-empty APP_KEY.
     */
    public function test_laravel_uses_configured_app_key(): void
    {
        $envKey = config('app.key');

        $this->assertNotEmpty($envKey, 'App key should be configured');
        $this->assertMatchesRegularExpression('/^base64:/', $envKey, 'App key should be a base64 encoded Laravel key');
    }
}
