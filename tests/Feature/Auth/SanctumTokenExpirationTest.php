<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class SanctumTokenExpirationTest extends TestCase
{
    /**
     * Test that Sanctum token expiration is configured to 60 minutes.
     */
    public function test_sanctum_token_expiration_is_set(): void
    {
        $this->assertSame(60, config('sanctum.expiration'), 'Sanctum token expiration should be 60 minutes');
    }
}
