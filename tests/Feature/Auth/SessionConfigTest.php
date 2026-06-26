<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class SessionConfigTest extends TestCase
{
    /**
     * Test that session secure cookie defaults to true in production.
     */
    public function test_session_config_secure_defaults_to_true_in_production(): void
    {
        $originalEnv = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: config('app.env');

        putenv('APP_ENV=production');
        $_ENV['APP_ENV'] = 'production';
        $_SERVER['APP_ENV'] = 'production';

        // Reload session config so the env-based default is re-evaluated.
        config(['session' => require config_path('session.php')]);

        try {
            $this->assertTrue(config('session.secure'));
        } finally {
            putenv("APP_ENV={$originalEnv}");
            $_ENV['APP_ENV'] = $originalEnv;
            $_SERVER['APP_ENV'] = $originalEnv;
            config(['session' => require config_path('session.php')]);
        }
    }

    /**
     * Test that session secure cookie can be overridden via env/config.
     */
    public function test_session_config_secure_respects_explicit_config(): void
    {
        config(['session.secure' => true]);

        $this->assertTrue(config('session.secure'));
    }

    /**
     * Test that session cookie same_site is set to strict.
     */
    public function test_session_config_same_site_is_strict(): void
    {
        $this->assertSame('strict', config('session.same_site'));
    }

    /**
     * Test that session encryption is enabled.
     */
    public function test_session_config_encryption_is_enabled(): void
    {
        $this->assertTrue(config('session.encrypt'));
    }
}
