<?php

namespace Tests\Feature;

use App\Enums\KpiMetric;
use App\Models\Bi\KpiTarget;
use App\Models\Branch;
use App\Models\User;
use App\Services\Bi\KpiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminKpiTargetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_targets_page_requires_the_threshold_permission(): void
    {
        $this->get(route('admin.kpi-targets.index'))->assertRedirect();

        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->get(route('admin.kpi-targets.index'))->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get(route('admin.kpi-targets.index'))
            ->assertOk()
            ->assertSee('KPI Targets')
            ->assertSee('Volume (MYR)');
    }

    #[Test]
    public function a_branch_target_overrides_the_company_wide_one(): void
    {
        $branch = Branch::factory()->create();

        KpiTarget::create([
            'kpi_metric' => KpiMetric::VolumeMyr->value,
            'branch_id' => null,
            'period' => KpiTarget::PERIOD_DAY,
            'target_value' => '1000.0000',
        ]);
        KpiTarget::create([
            'kpi_metric' => KpiMetric::VolumeMyr->value,
            'branch_id' => $branch->id,
            'period' => KpiTarget::PERIOD_DAY,
            'target_value' => '500.0000',
        ]);

        $targets = app(KpiService::class)->targets($branch->id);
        $this->assertSame('500.0000', $targets[KpiMetric::VolumeMyr->value]['target_value']);

        // A company-wide viewer only sees the null-branch row.
        $company = app(KpiService::class)->targets(null);
        $this->assertSame('1000.0000', $company[KpiMetric::VolumeMyr->value]['target_value']);
    }

    #[Test]
    public function saving_a_target_persists_it(): void
    {
        $admin = User::factory()->admin()->create();
        $branch = Branch::factory()->create();

        $this->actingAs($admin)
            ->withSession($this->passwordConfirmedSession())
            ->post(route('admin.kpi-targets.update'), [
                'targets' => [
                    KpiMetric::VolumeMyr->value.'|all|'.KpiTarget::PERIOD_DAY => '2500',
                    KpiMetric::TxCount->value.'|'.$branch->id.'|'.KpiTarget::PERIOD_MONTH => '40',
                ],
            ])
            ->assertRedirect(route('admin.kpi-targets.index'));

        $this->assertDatabaseHas('kpi_targets', [
            'kpi_metric' => KpiMetric::VolumeMyr->value,
            'branch_id' => null,
            'period' => KpiTarget::PERIOD_DAY,
            'target_value' => '2500.0000',
        ]);
        $this->assertDatabaseHas('kpi_targets', [
            'kpi_metric' => KpiMetric::TxCount->value,
            'branch_id' => $branch->id,
            'period' => KpiTarget::PERIOD_MONTH,
            'target_value' => '40.0000',
        ]);
    }

    #[Test]
    public function the_update_rejects_unknown_metrics_branches_and_periods(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->withSession($this->passwordConfirmedSession())
            ->post(route('admin.kpi-targets.update'), [
                'targets' => [
                    'not_a_metric|all|day' => '10',
                    KpiMetric::VolumeMyr->value.'|99999|day' => '10',
                    KpiMetric::VolumeMyr->value.'|all|year' => '10',
                ],
            ])
            ->assertSessionHasErrors();
    }

    #[Test]
    public function the_installer_is_registered_and_idempotent(): void
    {
        // The test schema already carries the table (SchemaSeeder is the DDL
        // source of truth), so this exercises the skip path. Creation is not
        // tested here for the same reason the other install-* commands are
        // not: RefreshDatabase's transaction makes DDL assertions unreliable.
        /** @var PendingCommand $command */
        $command = $this->artisan('kpi:install-targets-table');
        $command->expectsOutputToContain('already exists')->assertSuccessful();
    }

    #[Test]
    public function the_dashboard_shows_a_configured_target(): void
    {
        $admin = User::factory()->admin()->create();

        KpiTarget::create([
            'kpi_metric' => KpiMetric::VolumeMyr->value,
            'branch_id' => null,
            'period' => KpiTarget::PERIOD_DAY,
            'target_value' => '1000.0000',
        ]);

        $content = (string) $this->actingAs($admin)->get(route('bi.dashboard'))->getContent();

        $this->assertStringContainsString('Target 1,000.00', $content);
    }
}
