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
        // APP_ENV=testing under phpunit: the secure flag must resolve from the
        // environment (production-only default), not be hardcoded on.
        $this->assertFalse(config('session.secure'));
    }
}
