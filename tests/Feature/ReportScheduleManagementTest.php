<?php

namespace Tests\Feature;

use App\Enums\ReportType;
use App\Enums\UserRole;
use App\Models\ReportSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Report schedule lifecycle management (reports.schedules.*): pause, resume,
 * update, and destroy — the scheduling surface behind the automated BNM
 * report runs. Route group is gated by manage_report_schedules (admin only
 * in the default matrix).
 */
class ReportScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    }

    #[Test]
    public function pause_deactivates_an_active_schedule(): void
    {
        $schedule = $this->schedule(['is_active' => true]);

        $this->actingAs($this->admin)
            ->post(route('reports.schedules.pause', $schedule))
            ->assertRedirect(route('reports.schedules.show', $schedule))
            ->assertSessionHas('success', 'Report schedule paused.');

        $schedule->refresh();
        $this->assertFalse($schedule->is_active, 'Pause must deactivate the schedule');
    }

    #[Test]
    public function resume_reactivates_and_recomputes_the_next_run(): void
    {
        $schedule = $this->schedule(['is_active' => false]);

        $this->actingAs($this->admin)
            ->post(route('reports.schedules.resume', $schedule))
            ->assertRedirect(route('reports.schedules.show', $schedule))
            ->assertSessionHas('success', 'Report schedule resumed.');

        $schedule->refresh();
        $this->assertTrue($schedule->is_active, 'Resume must reactivate the schedule');
        $this->assertNotNull($schedule->next_run_at, 'Resume must recompute next_run_at');
        $this->assertTrue($schedule->next_run_at->isFuture(), 'next_run_at must be in the future after resume');
    }

    #[Test]
    public function update_changes_the_cron_expression(): void
    {
        $schedule = $this->schedule(['cron_expression' => '0 0 * * *']);

        $this->actingAs($this->admin)
            ->put(route('reports.schedules.update', $schedule), [
                'cron_expression' => '0 6 * * *',
            ])
            ->assertRedirect(route('reports.schedules.show', $schedule))
            ->assertSessionHas('success', 'Report schedule updated successfully.');

        $schedule->refresh();
        $this->assertSame('0 6 * * *', $schedule->cron_expression);
    }

    #[Test]
    public function update_rejects_an_invalid_cron_expression(): void
    {
        $schedule = $this->schedule(['cron_expression' => '0 0 * * *']);

        $this->actingAs($this->admin)
            ->put(route('reports.schedules.update', $schedule), [
                'cron_expression' => 'not-a-cron',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('cron_expression');

        $schedule->refresh();
        $this->assertSame('0 0 * * *', $schedule->cron_expression, 'An invalid update must not change the schedule');
    }

    #[Test]
    public function destroy_deletes_the_schedule(): void
    {
        $schedule = $this->schedule();

        $this->actingAs($this->admin)
            ->delete(route('reports.schedules.destroy', $schedule))
            ->assertRedirect(route('reports.schedules.index'))
            ->assertSessionHas('success', 'Report schedule deleted successfully.');

        $this->assertDatabaseMissing('report_schedules', ['id' => $schedule->id]);
    }

    #[Test]
    public function manager_without_manage_report_schedules_is_forbidden(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $schedule = $this->schedule();

        $this->actingAs($manager)
            ->post(route('reports.schedules.pause', $schedule))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function schedule(array $overrides = []): ReportSchedule
    {
        $schedule = ReportSchedule::create(array_merge([
            'report_type' => ReportType::Msb2,
            'cron_expression' => '0 0 * * *',
            'parameters' => null,
            'is_active' => true,
            'notification_recipients' => null,
            'created_by' => $this->admin->id,
        ], $overrides));
        $schedule->forceFill(['next_run_at' => now()->addDay()])->save();

        return $schedule->refresh();
    }
}
