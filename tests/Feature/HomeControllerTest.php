<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_setup_redirects_to_setup(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/setup');
    }

    public function test_authenticated_user_redirects_to_dashboard(): void
    {
        Currency::factory()->create();
        ExchangeRate::factory()->create();
        Branch::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect('/dashboard');
    }

    public function test_guest_redirects_to_login(): void
    {
        Currency::factory()->create();
        ExchangeRate::factory()->create();
        Branch::factory()->create();
        User::factory()->create();

        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
