<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class SessionSameSiteTest extends TestCase
{
    /**
     * Test that session cookies use SameSite=strict for CSRF protection.
     */
    public function test_session_same_site_is_strict(): void
    {
        $configFile = file_get_contents(base_path('config/session.php'));
        $this->assertStringContainsString(
            "'same_site' => 'strict',",
            $configFile,
            'Session same_site should be strict'
        );
    }
}
