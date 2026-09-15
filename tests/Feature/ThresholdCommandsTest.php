<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SystemAlert;
use App\Models\ThresholdAudit;
use App\Models\User;
use App\Services\ThresholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ThresholdCommandsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function thresholds_show_lists_all_config_keys(): void
    {
        Artisan::call('thresholds:show');
        $output = Artisan::output();

        $this->assertStringContainsString('approval.auto_approve', $output);
        $this->assertStringContainsString('position_limits.usd', $output);
        $this->assertStringContainsString('geographic_risk.high_country_weight', $output);
        $this->assertStringContainsString('config', $output);
    }

    #[Test]
    public function thresholds_show_category_filter(): void
    {
        Artisan::call('thresholds:show', ['--category' => 'variance']);
        $output = Artisan::output();

        $this->assertStringContainsString('variance.yellow', $output);
        $this->assertStringNotContainsString('approval.auto_approve', $output);
    }

    #[Test]
    public function thresholds_show_rejects_unknown_category(): void
    {
        $exitCode = Artisan::call('thresholds:show', ['--category' => 'nope']);

        $this->assertSame(1, $exitCode);
    }

    #[Test]
    public function thresholds_show_marks_db_overrides_and_warns(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        app(ThresholdService::class)->set('approval', 'auto_approve', '15000', 'ops bump');

        Artisan::call('thresholds:show', ['--overrides' => true]);
        $output = Artisan::output();

        $this->assertStringContainsString('approval.auto_approve', $output);
        $this->assertStringContainsString('15000', $output);
        $this->assertStringContainsString('db', $output);
        $this->assertStringNotContainsString('variance.yellow', $output);
    }

    #[Test]
    public function thresholds_reset_restores_config_value_with_audit_row(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        app(ThresholdService::class)->set('approval', 'auto_approve', '15000', 'bump');

        $exitCode = Artisan::call('thresholds:reset', [
            'category' => 'approval',
            'key' => 'auto_approve',
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('15000', $output);

        // Reset is append-only: the reverting value is a new audit row.
        $latest = ThresholdAudit::where('category', 'approval')
            ->where('key', 'auto_approve')
            ->latest('id')
            ->first();

        $this->assertNotNull($latest);
        $this->assertEquals('15000', $latest->old_value);
        $this->assertEquals('10000', $latest->new_value);

        // A fresh service instance now resolves the config value again.
        $this->assertEquals('10000', (new ThresholdService)->getAutoApproveThreshold());
    }

    #[Test]
    public function thresholds_reset_noop_without_override(): void
    {
        $exitCode = Artisan::call('thresholds:reset', [
            'category' => 'approval',
            'key' => 'auto_approve',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('no active DB override', Artisan::output());
    }

    #[Test]
    public function thresholds_reset_rejects_unknown_key(): void
    {
        $exitCode = Artisan::call('thresholds:reset', [
            'category' => 'approval',
            'key' => 'nonexistent',
        ]);

        $this->assertSame(1, $exitCode);
    }

    #[Test]
    public function check_overrides_succeeds_when_none_active(): void
    {
        $exitCode = Artisan::call('thresholds:check-overrides');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('No active threshold overrides', Artisan::output());
    }

    #[Test]
    public function check_overrides_warns_but_succeeds_without_fail_flag(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        app(ThresholdService::class)->set('approval', 'auto_approve', '15000', 'bump');

        $exitCode = Artisan::call('thresholds:check-overrides');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('approval.auto_approve', Artisan::output());
    }

    #[Test]
    public function check_overrides_fails_with_fail_flag_and_lists_overrides(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        app(ThresholdService::class)->set('approval', 'auto_approve', '15000', 'bump');

        $exitCode = Artisan::call('thresholds:check-overrides', ['--fail' => true]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('approval.auto_approve', Artisan::output());
    }

    #[Test]
    public function check_overrides_alert_is_deduplicated(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        app(ThresholdService::class)->set('approval', 'auto_approve', '15000', 'bump');

        Artisan::call('thresholds:check-overrides', ['--alert' => true]);
        $this->assertEquals(1, SystemAlert::where('source', 'thresholds')->count());

        // Same override set: no second alert while the first is unacknowledged.
        Artisan::call('thresholds:check-overrides', ['--alert' => true]);
        $this->assertEquals(1, SystemAlert::where('source', 'thresholds')->count());

        // Acknowledging clears the dedupe — the next run re-alerts.
        SystemAlert::where('source', 'thresholds')->first()->acknowledge(1);
        Artisan::call('thresholds:check-overrides', ['--alert' => true]);
        $this->assertEquals(2, SystemAlert::where('source', 'thresholds')->count());
    }
}
