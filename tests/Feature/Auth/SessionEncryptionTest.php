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
        $this->assertTrue(config('session.encrypt'));
    }
}
