<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class SessionEncryptionTest extends TestCase
{
    /**
     * Test that session data encryption is enabled.
     */
    public function test_session_encryption_is_enabled(): void
    {
        $configFile = file_get_contents(base_path('config/session.php'));
        $this->assertStringContainsString(
            "'encrypt' => true,",
            $configFile,
            'Session encryption should be enabled'
        );
    }
}
