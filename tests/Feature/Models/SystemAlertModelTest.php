<?php

namespace Tests\Feature\Models;

use App\Enums\SystemAlertLevel;
use App\Models\SystemAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemAlertModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_retrieves_info_alerts_via_scope(): void
    {
        $alert = SystemAlert::create([
            'level' => SystemAlertLevel::Info,
            'message' => 'Test info',
            'source' => 'test',
        ]);

        $this->assertTrue(SystemAlert::info()->where('id', $alert->id)->exists());
    }

    public function test_acknowledge_sets_user_and_timestamp(): void
    {
        $user = User::factory()->create();
        $alert = SystemAlert::create([
            'level' => SystemAlertLevel::Warning,
            'message' => 'Test warning',
            'source' => 'test',
        ]);

        $alert->acknowledge($user->id);

        $this->assertDatabaseHas('system_alerts', [
            'id' => $alert->id,
            'acknowledged_by' => $user->id,
        ]);
        $this->assertTrue($alert->fresh()->isAcknowledged());
    }

    public function test_status_accessors_work(): void
    {
        $alert = new SystemAlert([
            'level' => SystemAlertLevel::Critical,
        ]);
        $this->assertEquals('status-flagged', $alert->statusBadgeClass);
        $this->assertEquals('Critical', $alert->statusLabel);
    }

    public function test_today_scope_filters_today_alerts(): void
    {
        $alert = SystemAlert::create([
            'level' => SystemAlertLevel::Info,
            'message' => 'Today',
            'source' => 'test',
        ]);

        $this->assertTrue(SystemAlert::today()->where('id', $alert->id)->exists());
    }

    public function test_latest_scope_orders_by_created_at_desc(): void
    {
        $older = SystemAlert::create([
            'level' => SystemAlertLevel::Info,
            'message' => 'Older',
            'source' => 'test',
            'created_at' => now()->subDay(),
        ]);
        $newer = SystemAlert::create([
            'level' => SystemAlertLevel::Info,
            'message' => 'Newer',
            'source' => 'test',
        ]);

        $result = SystemAlert::latest()->pluck('id')->toArray();

        $this->assertSame([$newer->id, $older->id], $result);
    }
}
