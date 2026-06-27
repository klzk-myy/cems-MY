<?php

namespace Tests\Feature\Audit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_response_includes_csp_header(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get('/dashboard')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_api_routes_do_not_set_session_cookie(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson(route('api.v1.user'));

        $this->assertEmpty($response->headers->getCookies());
    }
}
