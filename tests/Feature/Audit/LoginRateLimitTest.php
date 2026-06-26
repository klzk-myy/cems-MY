<?php

namespace Tests\Feature\Audit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    public function test_login_route_has_throttle_middleware(): void
    {
        $route = Route::getRoutes()->match(Request::create('/login', 'POST'));

        $this->assertNotNull($route);
        $this->assertContains('throttle:login', $route->gatherMiddleware());
    }
}
