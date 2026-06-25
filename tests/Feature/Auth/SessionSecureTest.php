<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class SessionSecureTest extends TestCase
{
    /**
     * Test that session cookie secure setting defaults to production environment check.
     */
    public function test_session_secure_has_production_fallback(): void
    {
        $configFile = file_get_contents(base_path('config/session.php'));
        $this->assertStringContainsString(
            "'secure' => env('SESSION_SECURE_COOKIE', \$_ENV['APP_ENV'] === 'production')",
            $configFile,
            'Session secure config should fallback to production environment check'
        );
    }
}
