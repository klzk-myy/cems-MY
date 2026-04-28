<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HomeDashboardN1Test extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function home_dashboard_uses_caching_and_has_reduced_queries()
    {
        Cache::flush();

        // Create a user and authenticate
        $user = User::factory()->create();
        $this->actingAs($user);

        // Enable query log
        \DB::enableQueryLog();

        $response = $this->get('/dashboard');
        $response->assertStatus(200);

        // Check that caching is used (cache hits should exist)
        $cacheStats = Cache::get('dashboard_cache_stats');
        $this->assertNotNull($cacheStats, 'Dashboard should track cache statistics');

        // Verify queries are significantly reduced (should be < 100, not thousands)
        $queryLog = \DB::getQueryLog();
        $this->assertLessThan(
            100,
            count($queryLog),
            'Dashboard should use caching to reduce query count from 4000-8000 to under 100'
        );
    }
}
