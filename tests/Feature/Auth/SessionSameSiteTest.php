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
        $this->assertSame('strict', config('session.same_site'));
    }
}
