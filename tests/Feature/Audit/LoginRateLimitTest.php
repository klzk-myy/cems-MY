<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    public function test_login_route_has_throttle_middleware(): void
    {
        $file = base_path('routes/auth.php');
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertStringContainsString("->middleware('throttle:login')", $content, 'Login POST should have throttle:login middleware');
    }
}
