<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceMonitoringControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_performance_dashboard_accessible_by_admin()
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $response = $this->actingAs($admin)->get('/performance');
        $response->assertStatus(200);
        $response->assertViewIs('performance.index');
    }

    public function test_performance_dashboard_shows_metrics()
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $response = $this->actingAs($admin)->get('/performance');
        $response->assertStatus(200);
        $response->assertViewHas('metrics');
    }
}
