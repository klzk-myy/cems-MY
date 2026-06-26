<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class SessionConfigTest extends TestCase
{
    /**
     * Test that session cookie secure setting defaults to production environment check.
     */
    public function test_session_config_secure_has_production_fallback(): void
    {
        $configFile = file_get_contents(base_path('config/session.php'));
        $this->assertStringContainsString("'secure' => env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')", $configFile);
    }

    /**
     * Test that session cookie same_site is set to strict.
     */
    public function test_session_config_same_site_is_strict(): void
    {
        $configFile = file_get_contents(base_path('config/session.php'));
        $this->assertStringContainsString("'same_site' => 'strict',", $configFile);
    }

    /**
     * Test that session encryption is enabled.
     */
    public function test_session_config_encryption_is_enabled(): void
    {
        $configFile = file_get_contents(base_path('config/session.php'));
        $this->assertStringContainsString("'encrypt' => true,", $configFile);
    }
}
